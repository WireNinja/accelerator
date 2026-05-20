<?php

namespace WireNinja\Accelerator\Composer;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;

/**
 * @DONOT-REMOVE entire class
 *
 * Composer plugin that creates the `resources/svg` folder in the project root every
 * time `post-autoload-dump` fires. blade-icons (vendor) will throw an error on boot
 * if `resources/svg` does not exist — even when no custom icons are used.
 *
 * The activate / deactivate / uninstall methods are intentionally empty: PluginInterface
 * requires them, but we have no additional hooks needed. Do NOT convert to abstract or remove.
 */
class Plugin implements EventSubscriberInterface, PluginInterface
{
    public function activate(Composer $composer, IOInterface $io)
    {
        // No-op: required by PluginInterface contract.
    }

    public function deactivate(Composer $composer, IOInterface $io)
    {
        // No-op: required by PluginInterface contract.
    }

    public function uninstall(Composer $composer, IOInterface $io)
    {
        // No-op: required by PluginInterface contract. The resources/svg folder is
        // intentionally not removed on uninstall as it may contain user files.
    }

    public static function getSubscribedEvents()
    {
        return [
            'post-autoload-dump' => 'onPostAutoloadDump',
        ];
    }

    public function onPostAutoloadDump(Event $event)
    {
        $vendorPath = $event->getComposer()->getConfig()->get('vendor-dir');
        $rootPath = dirname($vendorPath);
        $svgPath = $rootPath.'/resources/svg';

        if (! is_dir($svgPath)) {
            if (mkdir($svgPath, 0755, true)) {
                $event->getIO()->write('<info>Accelerator:</info> Created missing <comment>resources/svg</comment> directory to prevent blade-icons error.');
            }
        }
    }
}
