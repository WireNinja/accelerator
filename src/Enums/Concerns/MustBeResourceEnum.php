<?php

namespace WireNinja\Accelerator\Enums\Concerns;

interface MustBeResourceEnum
{
    public function getLabel(): string;

    public function getResource(): string;

    public function getNavigationIcon(): string;

    public function getNavigationGroup(): string;

    public function getPanelGroup(): string;

    public static function fromResource(string $resource): ?self;

    public static function getResourcesPermissions(): array;
}
