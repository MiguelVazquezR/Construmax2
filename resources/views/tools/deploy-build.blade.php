<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Desplegar build — {{ config('app.name', 'Laravel') }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f3f4f6;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
            padding: 32px;
            width: 100%;
            max-width: 480px;
        }
        h1 { font-size: 20px; color: #111827; margin-bottom: 8px; }
        p.subtitle { font-size: 14px; color: #6b7280; margin-bottom: 24px; line-height: 1.5; }
        form { display: flex; flex-direction: column; gap: 16px; }
        label { font-size: 13px; font-weight: 600; color: #374151; }
        input[type="file"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 14px;
            background: #f9fafb;
            cursor: pointer;
        }
        button {
            background: #f26c17;
            color: #ffffff;
            border: none;
            border-radius: 8px;
            padding: 12px 16px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        button:hover { background: #d95d0f; }
        button:disabled { background: #9ca3af; cursor: not-allowed; }
        .alert {
            border-radius: 8px;
            padding: 12px 16px;
            font-size: 14px;
            line-height: 1.5;
            margin-bottom: 16px;
        }
        .alert-success { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
        .alert-error { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
        ul.errors { margin: 8px 0 0 16px; font-size: 13px; }
        .hint { font-size: 12px; color: #9ca3af; margin-top: 4px; }
        a.back { display: inline-block; margin-top: 16px; font-size: 13px; color: #6b7280; text-decoration: none; }
        a.back:hover { color: #111827; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Desplegar build</h1>
        <p class="subtitle">
            Sube el archivo <strong>build.zip</strong> generado con <code>npm run build</code>.
            El servidor eliminará la carpeta <code>public/build</code> anterior y extraerá la nueva en su lugar.
        </p>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-error">
                <strong>Ocurrieron los siguientes errores:</strong>
                <ul class="errors">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('tools.deploy-build.deploy') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <label for="build_zip">Archivo build.zip</label>
            <input type="file" name="build_zip" id="build_zip" accept=".zip,application/zip" required>
            <p class="hint">Peso máximo: 20MB.</p>
            <button type="submit" id="submitBtn">Subir build</button>
        </form>

        <a class="back" href="{{ url('/dashboard') }}">← Volver al dashboard</a>
    </div>

    <script>
        const form = document.querySelector('form');
        const submitBtn = document.getElementById('submitBtn');
        form.addEventListener('submit', () => {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Subiendo...';
        });
    </script>
</body>
</html>