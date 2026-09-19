<?php

namespace App\Providers;

use App\Services\Payroll\FaceRecognition\AwsFaceRecognitionService;
use App\Services\Payroll\FaceRecognition\FaceRecognitionService;
use App\Services\Payroll\FaceRecognition\NullFaceRecognitionService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Face recognition: use AWS Rekognition when credentials are present,
        // otherwise fall back to the null implementation (PIN kiosk keeps working).
        $this->app->bind(FaceRecognitionService::class, function () {
            $aws = new AwsFaceRecognitionService();

            return $aws->isConfigured() ? $aws : new NullFaceRecognitionService();
        });
    }
}
