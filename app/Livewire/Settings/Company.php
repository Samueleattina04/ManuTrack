<?php

namespace App\Livewire\Settings;

use App\Support\Access\Access;
use App\Support\Audit\Audit;
use App\Support\Brand\Brand;
use App\Support\Brand\Color;
use App\Support\Brand\CompanyLogo;
use App\Support\Brand\CustomIcons;
use App\Support\Brand\PaletteBuilder;
use App\Support\Security\AdminTwoFactor;
use App\Support\Setup\Requirements;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\File;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use RuntimeException;

/**
 * Impostazioni › Azienda: nome, logo del cliente, colori, lingua e fuso orario.
 */
class Company extends Component
{
    use WithFileUploads;

    public string $name = '';

    public string $palette = 'rame';

    public string $customAccent = '#2A5F2F';

    public string $customSoft = '#A8CB6E';

    public string $timezone = 'Europe/Rome';

    public string $locale = 'it';

    #[Validate(['logo' => 'nullable|file|mimes:png,jpg,jpeg,webp|mimetypes:image/png,image/jpeg,image/webp|max:2048'])]
    /** Verifica in due passaggi obbligatoria per gli amministratori */
    public bool $adminTwoFactor = false;

    public ?TemporaryUploadedFile $logo = null;

    #[Validate(['logoDark' => 'nullable|file|mimes:png,jpg,jpeg,webp|mimetypes:image/png,image/jpeg,image/webp|max:2048'])]
    public ?TemporaryUploadedFile $logoDark = null;

    public function mount(): void
    {
        $this->authorize('settings.manage');

        $this->name = (string) settings('company.name');
        $this->palette = Brand::paletteKey();
        $this->timezone = (string) settings('company.timezone', config('app.timezone'));
        $this->locale = (string) settings('company.locale', config('app.locale'));
        $this->adminTwoFactor = AdminTwoFactor::required();

        $custom = settings(Brand::CUSTOM_SETTING);
        if (is_array($custom)) {
            $this->customAccent = $custom['source_accent'] ?? $custom['accent_l'];
            $this->customSoft = $custom['source_soft'] ?? $custom['soft_d'];
        }
    }

    public function save(CustomIcons $icons): void
    {
        $this->authorize('settings.manage');

        $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'palette' => ['required', 'in:'.implode(',', [...array_keys((array) config('manutrack.palettes')), Brand::CUSTOM])],
            'customAccent' => ['required_if:palette,'.Brand::CUSTOM, 'nullable', 'regex:/^#?([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'customSoft' => ['nullable', 'regex:/^#?([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'timezone' => ['required', 'timezone:all'],
            'locale' => ['required', 'in:'.implode(',', $this->locales())],
        ], attributes: [
            'name' => __('settings.company.name'),
            'customAccent' => __('settings.colors.accent'),
            'customSoft' => __('settings.colors.soft'),
        ]);

        $before = [
            'company.name' => settings('company.name'),
            'company.palette' => settings('company.palette'),
            'company.timezone' => settings('company.timezone'),
            'company.locale' => settings('company.locale'),
        ];

        $values = [
            'company.name' => trim($this->name),
            'company.palette' => $this->palette,
            'company.timezone' => $this->timezone,
            'company.locale' => $this->locale,
        ];

        if ($this->palette === Brand::CUSTOM) {
            $built = PaletteBuilder::build($this->customAccent, $this->customSoft ?: null);
            $built['source_accent'] = Color::normalize($this->customAccent);
            $built['source_soft'] = $this->customSoft ? Color::normalize($this->customSoft) : null;
            $values[Brand::CUSTOM_SETTING] = $built;
        }

        settings()->setMany($values);

        if ($this->palette === Brand::CUSTOM) {
            $icons->generate(Brand::palette());
        }

        $changes = [];
        foreach ($before as $key => $old) {
            if ($old !== $values[$key]) {
                $changes[$key] = [$old, $values[$key]];
            }
        }
        if ($this->palette === Brand::CUSTOM) {
            $changes['company.custom_palette'] = [null, $values[Brand::CUSTOM_SETTING]['source_accent']];
        }
        Audit::log('settings.company_updated', null, null, $changes);

        // La pagina si ricarica per applicare colori e icone
        session()->flash('status', __('settings.saved'));
        $this->redirectRoute('settings.company', navigate: false);
    }

    public function updatedLogo(): void
    {
        $this->storeLogo('logo', CompanyLogo::LIGHT);
    }

    public function updatedLogoDark(): void
    {
        $this->storeLogo('logoDark', CompanyLogo::DARK);
    }

    private function storeLogo(string $field, string $variant): void
    {
        $this->authorize('settings.manage');
        $this->validateOnly($field);

        $file = $this->{$field};
        if (! $file instanceof TemporaryUploadedFile) {
            return;
        }

        try {
            $data = CompanyLogo::store($file, $variant);
        } catch (RuntimeException $e) {
            $this->addError($field, $e->getMessage());

            return;
        } finally {
            $file->delete();
            $this->{$field} = null;
        }

        Audit::log('settings.logo_uploaded', null, $variant.' · '.$data['width'].'×'.$data['height']);
        $this->dispatch('toast', message: __('settings.logo.uploaded'));
    }

    public function removeLogo(string $variant = CompanyLogo::LIGHT): void
    {
        $this->authorize('settings.manage');
        $variant = $variant === CompanyLogo::DARK ? CompanyLogo::DARK : CompanyLogo::LIGHT;
        CompanyLogo::remove($variant);
        Audit::log('settings.logo_removed', null, $variant);
        $this->dispatch('toast', message: __('settings.logo.removed'));
    }

    /**
     * Propone i colori del logo caricato (verde scuro per i pulsanti, tono chiaro per gli accenti).
     */
    public function sampleFromLogo(): void
    {
        $this->authorize('settings.manage');
        $sample = CompanyLogo::sampleColors();

        if ($sample === null) {
            $this->dispatch('toast', message: __('settings.colors.sample_failed'), tone: 'warn');

            return;
        }

        $this->palette = Brand::CUSTOM;
        $this->customAccent = $sample['accent'];
        $this->customSoft = $sample['soft'];
        $this->dispatch('toast', message: __('settings.colors.sampled'));
    }

    /**
     * @return list<string>
     */
    private function locales(): array
    {
        return collect(File::directories(lang_path()))->map(fn ($d) => basename($d))->values()->all();
    }

    /**
     * Solo l'amministratore, e solo se lui stesso l'ha già attivata (altrimenti resterebbe
     * chiuso fuori subito dopo aver salvato).
     */
    public function saveTwoFactor(): void
    {
        abort_unless(auth()->user()->hasRole(Access::ADMIN), 403);
        if ($this->adminTwoFactor && ! auth()->user()->hasTwoFactor()) {
            $this->addError('adminTwoFactor', __('settings.two_factor.enable_yours_first'));
            $this->adminTwoFactor = false;

            return;
        }
        $before = AdminTwoFactor::required();
        settings()->set(AdminTwoFactor::SETTING, $this->adminTwoFactor);
        if ($before !== $this->adminTwoFactor) {
            Audit::log('settings.admin_two_factor', null, $this->adminTwoFactor ? __('settings.two_factor.audit_on') : __('settings.two_factor.audit_off'));
        }
        $this->dispatch('toast', message: __('settings.saved'));
    }

    public function render(): View
    {
        $preview = null;
        if ($this->palette === Brand::CUSTOM && Color::isValid($this->customAccent) && ($this->customSoft === '' || Color::isValid($this->customSoft))) {
            $preview = PaletteBuilder::build($this->customAccent, $this->customSoft ?: null);
        } elseif ($this->palette !== Brand::CUSTOM) {
            $preview = config('manutrack.palettes.'.$this->palette);
        }

        return view('livewire.settings.company', [
            'isAdmin' => auth()->user()->hasRole(Access::ADMIN),
            'adminsWithout' => AdminTwoFactor::adminsWithout(),
            'palettes' => (array) config('manutrack.palettes'),
            'preview' => $preview,
            'contrast' => $preview ? [
                'button_dark' => Color::contrast($preview['accent_d'], $preview['on_accent_d']),
                'button_light' => Color::contrast($preview['accent_l'], $preview['on_accent_l']),
                'text_dark' => Color::contrast($preview['accent_fg_d'] ?? $preview['accent_d'], '#151A21'),
                'text_light' => Color::contrast($preview['accent_fg_l'] ?? $preview['accent_l'], '#FFFFFF'),
            ] : null,
            'logoUrl' => CompanyLogo::url(),
            'logoDarkUrl' => CompanyLogo::url(CompanyLogo::DARK),
            'imageDriver' => Requirements::imageDriver(),
            'timezones' => \DateTimeZone::listIdentifiers(\DateTimeZone::EUROPE),
            'locales' => $this->locales(),
        ])->title(__('settings.company.title'))->layoutData(['heading' => __('settings.company.title')]);
    }
}
