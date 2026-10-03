<?php

namespace App\Providers;

use App\Services\DigitalCopyRenderer;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // The font directory is a constructor argument rather than something the
        // renderer resolves for itself, so the path the PDF library reads its
        // metrics from is stated once here where it can be read and changed, and so
        // a test can point the renderer at a different directory if it ever needs
        // to prove the committed one is what was used.
        $this->app->bind(
            DigitalCopyRenderer::class,
            fn ($app): DigitalCopyRenderer => new DigitalCopyRenderer(
                resource_path('pdf-fonts')
            )
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
