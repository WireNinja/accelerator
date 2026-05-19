<?php

namespace WireNinja\Accelerator\Composer;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;

/**
 * @DONOT-REMOVE seluruh class
 *
 * Composer plugin yang membuat folder `resources/svg` di root project setiap kali
 * `post-autoload-dump` ter-trigger. blade-icons (vendor) akan throw error saat boot
 * jika folder `resources/svg` tidak ada — meskipun tidak ada custom icon yang dipakai.
 *
 * Method activate / deactivate / uninstall sengaja kosong: PluginInterface mewajibkan
 * implement, tapi kita tidak butuh hook tambahan. JANGAN convert ke abstract atau hapus.
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
        // No-op: required by PluginInterface contract. Folder resources/svg sengaja
        // tidak dihapus saat uninstall karena bisa berisi file user.
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
