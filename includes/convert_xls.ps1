param (
    [string]$xlsPath,
    [string]$csvPath
)

# Convert path strings to absolute paths to prevent issues with Excel COM Object
$xlsPath = [System.IO.Path]::GetFullPath($xlsPath)
$csvPath = [System.IO.Path]::GetFullPath($csvPath)

$excelPid = $null

# Add Win32 helper class to get PID from Window Handle (Hwnd)
$signature = @"
using System;
using System.Runtime.InteropServices;

public class Win32 {
    [DllImport("user32.dll")]
    public static extern uint GetWindowThreadProcessId(IntPtr hWnd, out uint lpdwProcessId);
    
    public static uint GetPid(int hwnd) {
        uint pid = 0;
        GetWindowThreadProcessId((IntPtr)hwnd, out pid);
        return pid;
    }
}
"@

try {
    Add-Type -TypeDefinition $signature -ErrorAction SilentlyContinue
} catch {}

try {
    $excel = New-Object -ComObject Excel.Application
    $excel.Visible = $false
    $excel.DisplayAlerts = $false
    
    # Get the process ID of this specific Excel instance using its Hwnd
    $excelHwnd = $excel.Hwnd
    if ($excelHwnd) {
        $excelPid = [Win32]::GetPid($excelHwnd)
    }
    
    $wb = $excel.Workbooks.Open($xlsPath)
    # Save as xlCSV (format code 6)
    $wb.SaveAs($csvPath, 6)
    $wb.Close($false)
    $excel.Quit()
    
    # Properly release COM references
    [System.Runtime.InteropServices.Marshal]::ReleaseComObject($excel) | Out-Null
    [GC]::Collect()
    [GC]::WaitForPendingFinalizers()
    
    # Force kill the specific excel process if it didn't exit cleanly
    if ($excelPid) {
        Stop-Process -Id $excelPid -Force -ErrorAction SilentlyContinue
    }
    
    Write-Host "Success"
    exit 0
} catch {
    # Attempt to kill Excel if we have its PID
    if ($excelPid) {
        Stop-Process -Id $excelPid -Force -ErrorAction SilentlyContinue
    }
    Write-Error "Error: $_"
    exit 1
}
