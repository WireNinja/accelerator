<?php

namespace WireNinja\Accelerator\Composer;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;

class Plugin implements EventSubscriberInterface, PluginInterface
{
    public function activate(Composer $composer, IOInterface $io)
    {
        //
    }

    public function deactivate(Composer $composer, IOInterface $io)
    {
        //
    }

    public function uninstall(Composer $composer, IOInterface $io)
    {
        //
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
