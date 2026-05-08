[CmdletBinding()]
param(
    [string]$SourceRepoPath = ".",
    [string]$MirrorRepoPath = "system/Storage/exports/history-rewrite/nosfirsolis-mirror.git",
    [string]$RemoteUrl = "",
    [switch]$PushMirror
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

function Invoke-GitChecked {
    param(
        [Parameter(Mandatory = $true)]
        [string[]]$Arguments
    )

    & git @Arguments
    if ($LASTEXITCODE -ne 0) {
        throw "Falha ao executar: git $($Arguments -join ' ')"
    }
}

function Resolve-AbsolutePath {
    param(
        [Parameter(Mandatory = $true)]
        [string]$PathValue,
        [Parameter(Mandatory = $true)]
        [string]$BasePath
    )

    if ([System.IO.Path]::IsPathRooted($PathValue)) {
        return [System.IO.Path]::GetFullPath($PathValue)
    }

    return [System.IO.Path]::GetFullPath((Join-Path $BasePath $PathValue))
}

function Ensure-GitFilterRepo {
    & git filter-repo --version 2>$null | Out-Null
    if ($LASTEXITCODE -ne 0) {
        throw "git-filter-repo nao encontrado. Instale com: pip install git-filter-repo"
    }
}

$sourceRepo = [System.IO.Path]::GetFullPath((Resolve-Path -LiteralPath $SourceRepoPath).Path)
$gitDir = Join-Path $sourceRepo ".git"
if (-not (Test-Path -LiteralPath $gitDir)) {
    throw "Repositorio Git nao encontrado em: $sourceRepo"
}

Ensure-GitFilterRepo

$mirrorRepo = Resolve-AbsolutePath -PathValue $MirrorRepoPath -BasePath $sourceRepo
$mirrorParent = Split-Path -Path $mirrorRepo -Parent

Write-Host "[1/6] Preparando clone espelho..."
if (Test-Path -LiteralPath $mirrorRepo) {
    Remove-Item -LiteralPath $mirrorRepo -Recurse -Force
}

if (-not (Test-Path -LiteralPath $mirrorParent)) {
    New-Item -ItemType Directory -Path $mirrorParent -Force | Out-Null
}

Invoke-GitChecked -Arguments @('-C', $sourceRepo, 'clone', '--mirror', '.', $mirrorRepo)

Write-Host "[2/6] Reescrevendo historico sensivel (config + sessoes)..."
$filterArgs = @(
    '-C', $mirrorRepo, 'filter-repo', '--force',
    '--path', 'system/Storage/config.php',
    '--path', 'system/Storage/config-local.php',
    '--path', 'system/Storage/config copy.php',
    '--path', 'system/storage/config.php',
    '--path', 'system/storage/config-local.php',
    '--path', 'system/storage/config copy.php',
    '--path-glob', 'system/Storage/sessions/sess_*',
    '--path-glob', 'system/storage/sessions/sess_*',
    '--invert-paths'
)
Invoke-GitChecked -Arguments $filterArgs

Write-Host "[3/6] Validando se os caminhos sensiveis sumiram do historico..."
$historyLines = & git -C $mirrorRepo log --all --name-only --pretty=format:
if ($LASTEXITCODE -ne 0) {
    throw "Falha ao coletar historico do mirror."
}

$sensitivePattern = '(?i)^system/(storage)/(config(\ copy|-local)?\.php|sessions/sess_)'
$matches = @($historyLines | Where-Object { $_ -match $sensitivePattern })
if ($matches.Count -gt 0) {
    $sample = $matches | Select-Object -First 10
    throw ("Historico ainda contem caminhos sensiveis: " + ($sample -join '; '))
}

Write-Host "[4/6] Validacao OK. Nenhum caminho sensivel encontrado no historico reescrito."

$newMain = (& git -C $mirrorRepo rev-parse --short refs/heads/main).Trim()
if ($LASTEXITCODE -ne 0) {
    throw "Falha ao obter hash da branch main no mirror."
}

Write-Host "[5/6] Mirror pronto em: $mirrorRepo"
Write-Host "        Nova main (mirror): $newMain"

if ($PushMirror) {
    $targetRemote = $RemoteUrl.Trim()
    if ($targetRemote -eq '') {
        $targetRemote = (& git -C $sourceRepo remote get-url origin 2>$null).Trim()
    }

    if ($targetRemote -eq '') {
        throw "Remote nao informado e origem nao encontrada. Use -RemoteUrl para definir o destino."
    }

    Write-Host "[6/6] Enviando historico reescrito para remote (force mirror)..."

    # Remove origem anterior do mirror (se houver), para evitar ambiguidade.
    $existingRemotes = @(& git -C $mirrorRepo remote)
    if ($LASTEXITCODE -ne 0) {
        throw "Falha ao listar remotes do mirror."
    }

    if ($existingRemotes -contains 'origin') {
        Invoke-GitChecked -Arguments @('-C', $mirrorRepo, 'remote', 'remove', 'origin')
    }

    Invoke-GitChecked -Arguments @('-C', $mirrorRepo, 'remote', 'add', 'origin', $targetRemote)

    Invoke-GitChecked -Arguments @('-C', $mirrorRepo, 'push', '--force', '--mirror', 'origin')
    Write-Host "Push concluido. Oriente a equipe a reclonar ou resetar branches locais."
} else {
    Write-Host "[6/6] Push NAO executado (modo seguro)."
    Write-Host "Para publicar a reescrita, execute:"
    Write-Host "  .\\tools\\security\\rewrite-sensitive-history.ps1 -PushMirror"
    Write-Host "Ou manualmente:"
    Write-Host "  git -C `"$mirrorRepo`" remote add origin <URL_DO_REMOTE>"
    Write-Host "  git -C `"$mirrorRepo`" push --force --mirror origin"
}
