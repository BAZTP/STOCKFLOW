<?php

namespace App\Http\Controllers;

use App\Http\Requests\SettingsRequest;
use App\Models\AuditLog;
use App\Models\CompanySetting;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class SettingsController extends Controller
{
    public function index()
    {
        Gate::authorize('settings.view');

        return view('settings.index', ['company' => CompanySetting::findOrFail(1)]);
    }

    public function update(SettingsRequest $r)
    {
        $data = $r->validated();
        unset($data['logo']);
        $logo = null;
        if ($r->hasFile('logo')) {
            $logo = $r->file('logo')->store('company', 'public');
            $data['logo_path'] = $logo;
        }try {
            \DB::transaction(function () use ($data, $r) {
                CompanySetting::findOrFail(1)->update($data);
                AuditLog::create(['user_id' => $r->user()->id, 'action' => 'company.updated', 'subject_type' => 'company_settings', 'subject_id' => 1]);
            });
        } catch (\Throwable $e) {
            if ($logo) {
                Storage::disk('public')->delete($logo);
            }throw $e;
        }

        return back()->with('status', 'Configuración guardada. Los documentos existentes conservan sus datos originales.');
    }
}
