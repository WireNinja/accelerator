{{--
  Accelerator Envoy bridge
  ------------------------

  This file intentionally delegates deployment logic to in-app Artisan
  commands. Envoy remains the SSH runner only.
--}}

@setup
    $targetStage = isset($stage) ? $stage : null;
    $targetService = isset($service) ? $service : 'all';
    $remotePath = isset($path) ? $path : null;

    $stageFlag = $targetStage ? '--stage='.$targetStage : '';
@endsetup

@story('deploy-next')
    ops-deploy
@endstory

@story('status-next')
    ops-status
@endstory

@story('restart-next')
    ops-restart
@endstory

@story('logs-next')
    ops-logs
@endstory

@task('ops-deploy', ['on' => 'vps'])
    @if($remotePath)
        cd {{ $remotePath }}
    @endif
    php artisan ops:deploy {{ $stageFlag }}
@endtask

@task('ops-status', ['on' => 'vps'])
    @if($remotePath)
        cd {{ $remotePath }}
    @endif
    php artisan ops:status {{ $stageFlag }}
@endtask

@task('ops-restart', ['on' => 'vps'])
    @if($remotePath)
        cd {{ $remotePath }}
    @endif
    php artisan ops:restart {{ $targetService }} {{ $stageFlag }}
@endtask

@task('ops-logs', ['on' => 'vps'])
    @if($remotePath)
        cd {{ $remotePath }}
    @endif
    php artisan ops:logs {{ $targetService }} {{ $stageFlag }}
@endtask
