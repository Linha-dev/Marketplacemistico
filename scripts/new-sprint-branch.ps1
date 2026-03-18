param(
  [Parameter(Mandatory = $true)]
  [ValidateSet("sprint", "hotfix")]
  [string]$Type,

  [Parameter(Mandatory = $false)]
  [string]$AuthorNick,

  [Parameter(Mandatory = $false)]
  [string]$CycleNumber,

  [Parameter(Mandatory = $false)]
  [string]$SprintNumber,

  [Parameter(Mandatory = $true)]
  [string]$Description,

  [switch]$NoPush
)

$ErrorActionPreference = "Stop"

function Normalize-Slug([string]$Text) {
  $normalized = $Text.Trim().ToLower()
  $normalized = $normalized -replace "[^a-z0-9]+", "-"
  $normalized = $normalized -replace "-{2,}", "-"
  return $normalized.Trim("-")
}

function Normalize-Author([string]$Text) {
  $raw = $Text.Trim()
  if ([string]::IsNullOrWhiteSpace($raw)) {
    return ""
  }
  $normalized = $raw -replace "[^A-Za-z0-9-]", ""
  return $normalized.Trim("-")
}

function Resolve-Author([string]$Author) {
  $candidate = Normalize-Author $Author
  if (-not [string]::IsNullOrWhiteSpace($candidate)) {
    return $candidate
  }

  $gitUser = (git config user.name 2>$null)
  $candidate = Normalize-Author $gitUser
  if (-not [string]::IsNullOrWhiteSpace($candidate)) {
    return $candidate
  }

  return "dev"
}

function Assert-TwoDigitNumber([string]$Value, [string]$Label) {
  if ([string]::IsNullOrWhiteSpace($Value)) {
    throw "$Label e obrigatorio para branch do tipo sprint."
  }

  $trimmed = $Value.Trim()
  if ($trimmed -notmatch "^\d{1,2}$") {
    throw "$Label deve ser numerico com 1 ou 2 digitos."
  }

  return "{0:D2}" -f [int]$trimmed
}

$author = Resolve-Author $AuthorNick
$slug = Normalize-Slug $Description

if ([string]::IsNullOrWhiteSpace($slug)) {
  throw "Description nao pode ficar vazia apos normalizacao."
}

$baseBranch = ""
$branchName = ""

if ($Type -eq "sprint") {
  $cyclePadded = Assert-TwoDigitNumber $CycleNumber "CycleNumber"
  $sprintPadded = Assert-TwoDigitNumber $SprintNumber "SprintNumber"
  $baseBranch = "develop"
  $branchName = "$author/c$cyclePadded-s$sprintPadded-$slug"
} else {
  $dateStamp = Get-Date -Format "yyyyMMdd"
  $baseBranch = "main"
  $branchName = "$author/hotfix-$dateStamp-$slug"
}

Write-Host "Atualizando $baseBranch..."
git checkout $baseBranch
git pull origin $baseBranch

Write-Host "Criando branch $branchName"
git checkout -b $branchName

if (-not $NoPush) {
  git push -u origin $branchName
}

Write-Host "Branch criada com sucesso: $branchName"
