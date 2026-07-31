<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Filament\Pages\Concerns;

use Filament\Resources\Pages\EditRecord;
use WireNinja\Accelerator\Filament\Concerns\LogsResourceRelationshipActivity;

abstract class AuditedEditRecord extends EditRecord
{
    use LogsResourceRelationshipActivity;
}
