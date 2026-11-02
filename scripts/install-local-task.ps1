$ErrorActionPreference = 'Stop'
$projectRoot = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$phpExecutable = (Get-Command php -ErrorAction Stop).Source
$syncScript = Join-Path $projectRoot 'sync.php'
if (-not (Test-Path -LiteralPath $syncScript -PathType Leaf)) { throw 'sync.php was not found.' }
$action = New-ScheduledTaskAction -Execute $phpExecutable -Argument ('"' + $syncScript + '" --days=7 --warm=3') -WorkingDirectory $projectRoot
$trigger = New-ScheduledTaskTrigger -Daily -At '00:15'
$settings = New-ScheduledTaskSettingsSet -StartWhenAvailable -RunOnlyIfNetworkAvailable -MultipleInstances IgnoreNew -ExecutionTimeLimit (New-TimeSpan -Minutes 15)
$principal = New-ScheduledTaskPrincipal -UserId ([System.Security.Principal.WindowsIdentity]::GetCurrent().Name) -LogonType Interactive -RunLevel Limited
Register-ScheduledTask -TaskName 'WDHOTD DailySync' -Action $action -Trigger $trigger -Settings $settings -Principal $principal -Description ('Publish the WDHOTD public archive. Project: ' + $projectRoot) -Force | Select-Object TaskName, State
