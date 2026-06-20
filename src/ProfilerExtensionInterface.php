<?php

declare(strict_types=1);

namespace PhpSoftBox\Profiler;

interface ProfilerExtensionInterface
{
    public function register(ProfilerRegistryInterface $registry): void;
}
