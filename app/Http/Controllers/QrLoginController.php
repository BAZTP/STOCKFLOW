<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

class QrLoginController extends Controller
{
    /**
     * Show a QR code that contains a signed login URL for the authenticated user.
     */
    public function show(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return redirect()->route('login');
        }

        // Get local network IPs (e.g., Wi-Fi or Ethernet) so phone on same WiFi can connect
        $availableIps = $this->getLocalIps();

        // Selected IP (default to Wi-Fi/first LAN IP, or query param)
        $selectedIp = $request->query('ip');
        if (!$selectedIp || !in_array($selectedIp, $availableIps)) {
            // Prefer 192.168.* IP
            $selectedIp = $availableIps[0] ?? $request->getHost();
            foreach ($availableIps as $ip) {
                // If there's an IP ending in .15 (common Wi-Fi) or any 192.168.*
                if (str_starts_with($ip, '192.168.')) {
                    $selectedIp = $ip;
                }
            }
        }

        // Generate signed route with relative path so any host/IP validates correctly
        $relativeSignedPath = URL::signedRoute('qr.login', ['id' => $user->id], now()->addDay(), absolute: false);

        // Build the full URL that the phone scanner will open
        $port = $request->getPort();
        $portStr = ($port && !in_array($port, [80, 443])) ? ":{$port}" : ':8000';
        $fullQrUrl = 'http://' . $selectedIp . $portStr . $relativeSignedPath;

        // Generate QR code with endroid/qr-code v6
        $qr = new QrCode($fullQrUrl, size: 280, margin: 10);
        $writer = new PngWriter();
        $result = $writer->write($qr);
        $qrDataUri = $result->getDataUri();

        return view('qr.login', [
            'qrDataUri' => $qrDataUri,
            'qrUrl' => $fullQrUrl,
            'user' => $user,
            'availableIps' => $availableIps,
            'selectedIp' => $selectedIp,
        ]);
    }

    /**
     * Handle the signed URL to authenticate the user and redirect to dashboard.
     */
    public function login(Request $request, $id)
    {
        Auth::loginUsingId($id);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('status', '¡Sesión iniciada correctamente desde tu celular!');
    }

    /**
     * Detect local network IPv4 addresses from Windows ipconfig.
     */
    private function getLocalIps(): array
    {
        $ips = [];
        $output = @shell_exec('ipconfig');
        if ($output) {
            preg_match_all('/IPv4.*?:\s*([0-9]+\.[0-9]+\.[0-9]+\.[0-9]+)/i', $output, $matches);
            if (!empty($matches[1])) {
                foreach ($matches[1] as $ip) {
                    if (!str_starts_with($ip, '127.')) {
                        $ips[] = $ip;
                    }
                }
            }
        }

        if (empty($ips)) {
            $hostnameIp = gethostbyname(gethostname());
            if ($hostnameIp && !str_starts_with($hostnameIp, '127.')) {
                $ips[] = $hostnameIp;
            }
        }

        return array_values(array_unique($ips));
    }
}
