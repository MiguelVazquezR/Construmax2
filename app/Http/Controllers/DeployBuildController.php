<?php

namespace App\Http\Controllers;

use App\Http\Requests\Tools\DeployBuildRequest;
use Illuminate\Support\Facades\File;
use ZipArchive;

class DeployBuildController extends Controller
{
    private const STAGING_PATH = 'app/deploy-staging';

    public function showForm()
    {
        $this->authorizeDeploy();

        return view('tools.deploy-build');
    }

    public function deploy(DeployBuildRequest $request)
    {
        $zipPath = $request->file('build_zip')->getRealPath();

        $this->validateZipContents($zipPath);

        $stagingRoot = storage_path(self::STAGING_PATH);
        $this->cleanDirectory($stagingRoot);

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            abort(422, 'No se pudo abrir el archivo ZIP.');
        }

        try {
            $zip->extractTo($stagingRoot);
        } finally {
            $zip->close();
        }

        $stagingBuild = $stagingRoot . '/build';
        if (!File::exists($stagingBuild . '/manifest.json')) {
            $this->cleanDirectory($stagingRoot);
            abort(422, 'El ZIP no contiene build/manifest.json. Asegurate de comprimir la carpeta correcta.');
        }

        // Swap: delete the previous build and copy the new one in its place.
        // The old folder is removed first to avoid stale files from older builds.
        // We use copy instead of move because rename() fails on some environments
        // (e.g. Windows) when the destination directory was recently deleted.
        $publicBuild = public_path('build');
        File::deleteDirectory($publicBuild);
        File::copyDirectory($stagingBuild, $publicBuild);
        File::deleteDirectory($stagingBuild);

        $this->cleanDirectory($stagingRoot);

        if (function_exists('opcache_reset')) {
            opcache_reset();
        }

        return back()->with('success', 'Build desplegado correctamente. Presiona Ctrl+F5 (o Cmd+Shift+R) para forzar la recarga en tu navegador.');
    }

    private function authorizeDeploy(): void
    {
        $user = auth()->user();

        if (!$user || !$user->can('permissions.manage')) {
            abort(403, 'No tienes permisos para desplegar builds.');
        }
    }

    /**
     * Inspecciona cada entrada del ZIP para rechazar archivos maliciosos
     * (path traversal, rutas absolutas, symlinks) y archivos fuera de build/.
     */
    private function validateZipContents(string $zipPath): void
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            abort(422, 'No se pudo abrir el archivo ZIP.');
        }

        $errors = [];

        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $name = $stat['name'];

                // Rechazar cualquier archivo que no este dentro de build/
                if (!str_starts_with($name, 'build/')) {
                    $errors[] = "Archivo fuera de build/: {$name}";
                }

                // Rechazar path traversal
                if (str_contains($name, '..')) {
                    $errors[] = "Path traversal detectado: {$name}";
                }

                // Rechazar rutas absolutas
                if (str_starts_with($name, '/') || preg_match('/^[A-Za-z]:[\\\\\\/]/', $name)) {
                    $errors[] = "Ruta absoluta detectada: {$name}";
                }

                // Rechazar symlinks (via external attributes unix)
                $externalAttr = $stat['external'] ?? 0;
                $isSymlink = ($externalAttr >> 16 & 0xA000) === 0xA000;
                if ($isSymlink) {
                    $errors[] = "Symlink detectado: {$name}";
                }
            }
        } finally {
            $zip->close();
        }

        if (!empty($errors)) {
            abort(422, 'El ZIP contiene entradas sospechosas o invalidas:<br>' . implode('<br>', array_slice($errors, 0, 10)));
        }
    }

    private function cleanDirectory(string $path): void
    {
        if (File::exists($path)) {
            File::deleteDirectory($path);
        }
        File::makeDirectory($path, 0755, true, true);
    }
}