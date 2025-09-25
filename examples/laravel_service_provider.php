<?php

declare(strict_types=1);

namespace App\Providers;

use Gamma\SDK\Config\ClientConfig;
use Gamma\SDK\GammaClient;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;

final class GammaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(GammaClient::class, function (Application $app): GammaClient {
            $config = ClientConfig::fromArray([
                'apiKey' => env('GAMMA_API_KEY'),
                'retries' => 3,
                'defaultHeaders' => [
                    'X-Workspace-ID' => env('GAMMA_WORKSPACE_ID', ''),
                ],
            ]);

            return GammaClient::createDefault(
                apiKey: $config->getApiKey(),
                config: $config,
                logger: $app->make(LoggerInterface::class),
            );
        });
    }
}
