# Week 14 security demo: shows each adverse behavior with a label, the HTTP status and the answer.
# Run from the repo root while these are running: Laravel (port 3000), the Caddy gateway (port 8443), MySQL.
#   cd C:\community-service-hours-tracker\backend-laravel
#   powershell -ExecutionPolicy Bypass -File ..\docs\security-demo.ps1
# It asks for the demo password (hidden). Tokens and the password are never printed.

$base  = 'https://localhost:8443'
$plain = 'http://localhost:3000'

$sec = Read-Host "Demo password" -AsSecureString
$pw  = [Runtime.InteropServices.Marshal]::PtrToStringAuto([Runtime.InteropServices.Marshal]::SecureStringToBSTR($sec))

function Call($Label, $Method, $Url, $Headers = @{}, $Body = $null) {
    Write-Host ""
    Write-Host "== $Label" -ForegroundColor Cyan
    $Headers['Accept'] = 'application/json'
    try {
        $p = @{ UseBasicParsing = $true; Method = $Method; Uri = $Url; Headers = $Headers }
        if ($Body) { $p.Body = ($Body | ConvertTo-Json); $p.ContentType = 'application/json' }
        $r = Invoke-WebRequest @p
        $code = [int]$r.StatusCode; $text = $r.Content
    } catch {
        $resp = $_.Exception.Response
        if (-not $resp) { Write-Host "No answer: $($_.Exception.Message)" -ForegroundColor Red; return }
        $code = [int]$resp.StatusCode
        $text = (New-Object System.IO.StreamReader($resp.GetResponseStream())).ReadToEnd()
    }
    Write-Host "Status: $code"
    Write-Host $text
}

function Login($email) {
    $r = Invoke-RestMethod -Method Post -Uri "$base/api/auth/login" -ContentType 'application/json' `
        -Body (@{ email = $email; password = $pw } | ConvertTo-Json)
    return @{ Authorization = "Bearer $($r.token)" }
}

Call "1. Wrong password -> 401 INVALID_CREDENTIALS" Post "$base/api/auth/login" @{} @{ email = 'student1@example.test'; password = 'not-the-password' }
Call "2. Unknown email -> same 401 and same message (no account guessing)" Post "$base/api/auth/login" @{} @{ email = 'nobody@example.test'; password = 'not-the-password' }
Call "3. Invalid input (bad email) -> 400 VALIDATION_ERROR" Post "$base/api/auth/login" @{} @{ email = 'not-an-email'; password = 'x' }
Call "4. No token -> 401 UNAUTHENTICATED" Get "$base/api/summaries/progress"

$student = Login 'student1@example.test'
$bad = @{ Authorization = ($student.Authorization.Substring(0, $student.Authorization.Length - 4) + 'AAAA') }
Call "5. Altered token -> 401 UNAUTHENTICATED" Get "$base/api/students/1/progress" $bad
Call "5b. Own progress with a good token -> 200 (control)" Get "$base/api/students/1/progress" @{ Authorization = $student.Authorization }
Call "6. Student reads student 2 -> 403 FORBIDDEN" Get "$base/api/students/2/progress" @{ Authorization = $student.Authorization }
Call "7. Student approves a submission -> 403 FORBIDDEN" Patch "$base/api/submissions/4/approve" @{ Authorization = $student.Authorization }

$adviser = Login 'adviser@example.test'
Call "8a. Adviser approves (read-only role) -> 403 FORBIDDEN" Patch "$base/api/submissions/4/approve" $adviser
$supervisor = Login 'supervisor@example.test'
Call "8b. Supervisor reads the progress summary -> 403 FORBIDDEN" Get "$base/api/summaries/progress" $supervisor

# Expired token: move the expiry of all tokens to the past (fictional demo data only), then use one.
php artisan tinker --execute="DB::table('personal_access_tokens')->update(['expires_at' => now()->subMinute()]);" | Out-Null
Call "9. Expired token (expiry moved to the past with tinker) -> 401 UNAUTHENTICATED" Get "$base/api/students/1/progress" @{ Authorization = $student.Authorization }

$coordinator = Login 'coordinator@example.test'
Call "10a. Coordinator signs out -> 200" Post "$base/api/auth/logout" $coordinator
Call "10b. The same token after sign-out -> 401 UNAUTHENTICATED (revoked)" Get "$base/api/summaries/progress" $coordinator

Call "11. Plain HTTP straight to the API -> 426 HTTPS_REQUIRED" Get "$plain/api/health"

Write-Host ""
Write-Host "== 12. Login limit: after 5 sign-in requests in one minute the next ones get 429 RATE_LIMITED" -ForegroundColor Cyan
foreach ($n in 1..6) {
    try {
        Invoke-WebRequest -UseBasicParsing -Method Post -Uri "$base/api/auth/login" -ContentType 'application/json' `
            -Headers @{ Accept = 'application/json' } -Body (@{ email = 'supervisor@example.test'; password = "wrong-$n" } | ConvertTo-Json) | Out-Null
    } catch {
        Write-Host ("Attempt {0}: Status {1}" -f $n, [int]$_.Exception.Response.StatusCode)
    }
}
Write-Host ""
Write-Host "Done. Wait one minute before signing in again (the login limit counts every sign-in request, including the earlier successful ones)." -ForegroundColor Yellow
