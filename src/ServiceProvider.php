<?php namespace AgelxNash\Evo\QThumb;

use EvolutionCMS\ServiceProvider as BaseServiceProvider;

class ServiceProvider extends BaseServiceProvider
{
    protected $namespace = 'qThumb';

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->loadSnippetsFrom(
            dirname(__DIR__). '/snippets/',
            $this->namespace
        );
    }
}
