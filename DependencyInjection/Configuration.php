<?php declare(strict_types = 1);

namespace Vairogs\Utils\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Vairogs\Auth\DependencyInjection\AuthDependency;
use Vairogs\Cache\DependencyInjection\CacheDependency;
use Vairogs\I18n\DependencyInjection\I18nDependency;
use Vairogs\Sitemap\DependencyInjection\SitemapDependency;
use Vairogs\Translation\DependencyInjection\TranslationDependency;
use Vairogs\Utils\Vairogs;

class Configuration implements ConfigurationInterface
{
    use DependecyLoaderTrait;

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder(name: Vairogs::VAIROGS);
        $rootNode = $treeBuilder->getRootNode();

        $this->appendComponent(class: CacheDependency::class, arrayNodeDefinition: $rootNode);
        $this->appendComponent(class: AuthDependency::class, arrayNodeDefinition: $rootNode);
        $this->appendComponent(class: SitemapDependency::class, arrayNodeDefinition: $rootNode);
        $this->appendComponent(class: TranslationDependency::class, arrayNodeDefinition: $rootNode);
        $this->appendComponent(class: I18nDependency::class, arrayNodeDefinition: $rootNode);

        return $treeBuilder;
    }
}
