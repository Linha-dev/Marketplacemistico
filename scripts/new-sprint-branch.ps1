param(
  [Parameter(Mandatory = $true)]
  [string]$SprintNumber,

  [Parameter(Mandatory = $true)]
  [string]$Description
)

$ErrorActionPreference = "Stop"

function Normalize-Text([string]$Text) {
  $normalized = $Text.Trim().ToLower()
  $normalized = $normalized -replace "[^a-z0-9]+", "-"
  $normalized = $normalized -replace "-{2,}", "-"
  return $normalized.Trim("-")
}

$sprint = $SprintNumber.Trim()
if ($sprint -notmatch "^\d{1,2}$") {
  throw "SprintNumber deve ser numerico (ex.: 7 ou 13)."
}

$sprintPadded = "{0:D2}" -f [int]$sprint
$slug = Normalize-Text $Description

if ([string]::IsNullOrWhiteSpace($slug)) {
  throw "Description nao pode ficar vazia apos normalizacao."
}

$branchName = "sprint/$sprintPadded-$slug"

Write-Host "Atualizando develop..."
git checkout develop
git pull origin develop

Write-Host "Criando branch $branchName"
git checkout -b $branchName
git push -u origin $branchName

Write-Host "Branch criada com sucesso: $branchName"
