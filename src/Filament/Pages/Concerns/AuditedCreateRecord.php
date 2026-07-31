<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Filament\Pages\Concerns;

use Filament\Resources\Pages\CreateRecord;
use WireNinja\Accelerator\Filament\Concerns\LogsResourceRelationshipActivity;

abstract class AuditedCreateRecord extends CreateRecord
{
    use LogsResourceRelationshipActivity;
}
