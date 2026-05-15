<?php

namespace App\Services;

use App\Models\Company;
use App\Support\MediaStorage;
use Illuminate\Support\Facades\File;

class BrandingService
{
    public const DEFAULT_PRIMARY = '#d4af37';

    public const DEFAULT_SECONDARY = '#223d69';

    public const DEFAULT_ACCENT = '#132746';

    /**
     * @return array{
     *   enabled: bool,
     *   primary: string,
     *   primary_hover: string,
     *   secondary: string,
     *   accent: string,
     *   logo_url: ?string,
     *   favicon_url: ?string,
     *   cover_url: ?string,
     *   public_headline: ?string,
     *   public_subheadline: ?string,
     *   welcome_message: ?string,
     *   custom_footer_text: ?string,
     *   company_name: string,
     *   description_fallback: ?string,
     *   hero_title: ?string,
     *   hero_subtitle: ?string,
     *   root_style: string,
     *   theme_light: bool,
     * }
     */
    public function getCurrentCompanyBranding(?Company $company): array
    {
        $fallbacks = $this->getFallbacks();

        if (! $company) {
            return [
                'enabled' => false,
                'primary' => $fallbacks['primary'],
                'primary_hover' => $this->mixColor($fallbacks['primary'], '#FFFFFF', 8),
                'secondary' => $fallbacks['secondary'],
                'accent' => $fallbacks['accent'],
                'logo_url' => null,
                'favicon_url' => null,
                'cover_url' => null,
                'public_headline' => null,
                'public_subheadline' => null,
                'welcome_message' => null,
                'custom_footer_text' => null,
                'company_name' => 'StudioFlow',
                'description_fallback' => null,
                'hero_title' => null,
                'hero_subtitle' => null,
                'root_style' => $this->buildRootStyleString(
                    $fallbacks['primary'],
                    $fallbacks['secondary'],
                    $fallbacks['accent'],
                ),
                'theme_light' => $this->isLightColor($fallbacks['secondary']),
            ];
        }

        $enabled = (bool) ($company->brand_enabled ?? true);
        $primary = $this->sanitizeColors($company->primary_color) ?? self::DEFAULT_PRIMARY;
        $secondary = $this->sanitizeColors($company->secondary_color) ?? self::DEFAULT_SECONDARY;
        $accent = $this->sanitizeColors($company->accent_color) ?? self::DEFAULT_ACCENT;

        if (! $enabled) {
            $primary = self::DEFAULT_PRIMARY;
            $secondary = self::DEFAULT_SECONDARY;
            $accent = self::DEFAULT_ACCENT;
        }

        return [
            'enabled' => $enabled,
            'primary' => $primary,
            'primary_hover' => $this->mixColor($primary, $this->isLightColor($secondary) ? '#000000' : '#FFFFFF', $this->isLightColor($secondary) ? 10 : 8),
            'secondary' => $secondary,
            'accent' => $accent,
            'logo_url' => $this->getLogoUrl($company),
            'favicon_url' => $this->getFaviconUrl($company),
            'cover_url' => $this->getCoverUrl($company),
            'public_headline' => $company->safeDisplayText($company->public_headline),
            'public_subheadline' => $company->safeDisplayText($company->public_subheadline),
            'welcome_message' => $company->safeDisplayText($company->welcome_message),
            'custom_footer_text' => $company->safeDisplayText($company->custom_footer_text),
            'company_name' => (string) $company->name,
            'description_fallback' => $company->safeDescription(),
            'hero_title' => $company->publicDisplayHeadline(),
            'hero_subtitle' => $company->publicDisplaySubheadline(),
            'root_style' => $this->buildRootStyleString($primary, $secondary, $accent),
            'theme_light' => $this->isLightColor($secondary),
        ];
    }

    /**
     * @return array{primary: string, secondary: string, accent: string}
     */
    public function getCssVariables(?Company $company): array
    {
        $b = $this->getCurrentCompanyBranding($company);

        return [
            'primary' => $b['primary'],
            'secondary' => $b['secondary'],
            'accent' => $b['accent'],
        ];
    }

    public function getLogoUrl(?Company $company): ?string
    {
        if (! $company) {
            return null;
        }

        $path = $company->normalizedLogoPath();

        return MediaStorage::url($path);
    }

    public function getFaviconUrl(?Company $company): ?string
    {
        if (! $company) {
            return null;
        }

        $path = $company->normalizedFaviconPath();

        return MediaStorage::url($path);
    }

    public function getCoverUrl(?Company $company): ?string
    {
        if (! $company) {
            return null;
        }

        $path = $company->normalizedCoverImagePath();

        return MediaStorage::url($path);
    }

    /**
     * @return array{primary: string, secondary: string, accent: string}
     */
    public function getFallbacks(): array
    {
        return [
            'primary' => self::DEFAULT_PRIMARY,
            'secondary' => self::DEFAULT_SECONDARY,
            'accent' => self::DEFAULT_ACCENT,
        ];
    }

    public function sanitizeColors(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $v = strtoupper(trim($value));

        if (preg_match('/^#[0-9A-F]{6}$/', $v) !== 1) {
            return null;
        }

        return $v;
    }

    public function faviconHrefFor(?Company $company): string
    {
        $url = $this->getCurrentCompanyBranding($company)['favicon_url'];

        return $url ?: $this->defaultFaviconHref() ?: asset('favicon.ico');
    }

    public function defaultFaviconHref(): string
    {
        $path = public_path('favicon.ico');

        return File::exists($path) ? asset('favicon.ico') : '';
    }

    /**
     * Cor de texto legível sobre botões / CTAs na cor primária (contraste mais estrito que texto de parágrafo).
     */
    public function contrastingForegroundOnPrimary(string $primaryHex): string
    {
        return $this->relativeLuminance($primaryHex) > 0.55 ? '#0F172A' : '#F8FAFC';
    }

    /**
     * Luminância relativa (0–1) para #RRGGBB.
     */
    public function relativeLuminance(string $hex): float
    {
        $rgb = $this->parseHexRgb($hex);
        if ($rgb === null) {
            return 0.0;
        }

        $lin = static fn (float $c): float => $c <= 0.03928
            ? $c / 12.92
            : (($c + 0.055) / 1.055) ** 2.4;

        $r = $lin($rgb[0] / 255);
        $g = $lin($rgb[1] / 255);
        $b = $lin($rgb[2] / 255);

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }

    public function isLightColor(string $hex): bool
    {
        return $this->relativeLuminance($hex) >= 0.52;
    }

    /**
     * Texto legível sobre um fundo sólido (não necessariamente primário).
     */
    public function readableTextColor(string $backgroundHex): string
    {
        return $this->relativeLuminance($backgroundHex) > 0.45 ? '#0F172A' : '#F8FAFC';
    }

    /**
     * Mistura linear de duas cores hex (#RRGGBB). $percentB é 0–100 (fração de B no resultado).
     */
    public function mixColor(string $hexA, string $hexB, float $percentB): string
    {
        $a = $this->parseHexRgb($hexA);
        $b = $this->parseHexRgb($hexB);
        if ($a === null || $b === null) {
            return strtoupper($hexA);
        }

        $t = max(0.0, min(100.0, $percentB)) / 100.0;
        $r = (int) round($a[0] + ($b[0] - $a[0]) * $t);
        $g = (int) round($a[1] + ($b[1] - $a[1]) * $t);
        $bl = (int) round($a[2] + ($b[2] - $a[2]) * $t);

        return sprintf('#%02X%02X%02X', $r, $g, $bl);
    }

    /**
     * CSS inline para pré-visualização em /company (mesma lógica do tema salvo).
     */
    public function previewThemeStyleString(?string $primary, ?string $secondary, ?string $accent, bool $brandEnabled): string
    {
        $pairs = $this->previewThemeStylePairs($primary, $secondary, $accent, $brandEnabled);
        $parts = [];
        foreach ($pairs as $key => $value) {
            $parts[] = $key.': '.$value;
        }

        return implode('; ', $parts);
    }

    /**
     * Mesmas variáveis do tema, como array (para JSON no preview / Alpine).
     *
     * @return array<string, string>
     */
    public function previewThemeStylePairs(?string $primary, ?string $secondary, ?string $accent, bool $brandEnabled): array
    {
        $f = $this->getFallbacks();

        if (! $brandEnabled) {
            return $this->globalThemeVariablePairs($f['primary'], $f['secondary'], $f['accent']);
        }

        $p = $this->sanitizeColors($primary) ?? $f['primary'];
        $s = $this->sanitizeColors($secondary) ?? $f['secondary'];
        $a = $this->sanitizeColors($accent) ?? $f['accent'];

        return $this->globalThemeVariablePairs($p, $s, $a);
    }

    /**
     * Tema global (CSS vars no elemento raiz HTML): marca + shell + componentes. Mantém aliases --sf-* para compatibilidade.
     */
    private function buildRootStyleString(string $primary, string $secondary, string $accent): string
    {
        $pairs = $this->globalThemeVariablePairs($primary, $secondary, $accent);
        $parts = [];
        foreach ($pairs as $key => $value) {
            $parts[] = $key.': '.$value;
        }

        return implode('; ', $parts);
    }

    /**
     * @return array<string, string>
     */
    private function globalThemeVariablePairs(string $primary, string $secondary, string $accent): array
    {
        $black = '#000000';
        $white = '#FFFFFF';
        $onPrimary = $this->contrastingForegroundOnPrimary($primary);
        $secondaryLight = $this->isLightColor($secondary);
        $primaryHover = $secondaryLight
            ? $this->mixColor($primary, $black, 10)
            : $this->mixColor($primary, $white, 8);

        $textMain = $this->readableTextColor($secondary);
        $textOnCard = $this->readableTextColor($accent);
        $slateMuted = $secondaryLight ? '#64748B' : '#94A3B8';

        $textMuted = $this->mixColor($textMain, $slateMuted, $secondaryLight ? 38 : 42);

        $appBg = $secondary;
        $appShellBg = $secondaryLight
            ? $this->mixColor($secondary, $black, 2.5)
            : $this->mixColor($secondary, $black, 5);

        $sidebarBg = $secondaryLight
            ? $this->mixColor($secondary, $primary, 7)
            : $this->mixColor($secondary, $black, 10);

        $topbarBg = $secondaryLight
            ? $this->mixColor($secondary, $black, 4)
            : $this->mixColor($secondary, $black, 8);

        $sidebarCardBg = $accent;

        $cardBg = $accent;
        $cardSoftBg = $secondaryLight
            ? $this->mixColor($accent, $black, 5)
            : $this->mixColor($accent, $white, 6);

        $cardBorder = $secondaryLight
            ? $this->mixColor($accent, $black, 12)
            : $this->mixColor($accent, $white, 10);

        $inputBg = $secondaryLight
            ? $this->mixColor($accent, $white, 55)
            : $this->mixColor($accent, $white, 5);

        $inputBorder = $secondaryLight
            ? $this->mixColor($accent, $black, 14)
            : $this->mixColor($accent, $white, 14);

        $linkColor = $primary;

        $btnPrimaryBg = $primary;
        $btnPrimaryText = $onPrimary;
        $btnPrimaryBorder = $this->mixColor($primary, $secondaryLight ? $black : $white, 18);

        $activeMenuBg = $this->mixColor($primary, $sidebarBg, $secondaryLight ? 12 : 18);
        $activeMenuBorder = $primary;
        $activeMenuText = $this->readableTextColor($activeMenuBg);

        $tableBg = $cardSoftBg;
        $tableBorder = $cardBorder;

        $badgeBg = $this->mixColor($primary, $secondaryLight ? $white : $black, 22);
        $badgeText = $this->readableTextColor($badgeBg);

        return [
            '--brand-primary' => $primary,
            '--brand-primary-hover' => $primaryHover,
            '--brand-secondary' => $secondary,
            '--brand-accent' => $accent,
            '--brand-on-primary' => $onPrimary,

            '--app-bg' => $appBg,
            '--app-shell-bg' => $appShellBg,
            '--sidebar-bg' => $sidebarBg,
            '--sidebar-card-bg' => $sidebarCardBg,
            '--topbar-bg' => $topbarBg,

            '--card-bg' => $cardBg,
            '--card-border' => $cardBorder,
            '--card-soft-bg' => $cardSoftBg,

            '--input-bg' => $inputBg,
            '--input-border' => $inputBorder,

            '--text-main' => $textMain,
            '--text-muted' => $textMuted,
            '--text-on-card' => $textOnCard,

            '--link-color' => $linkColor,

            '--btn-primary-bg' => $btnPrimaryBg,
            '--btn-primary-text' => $btnPrimaryText,
            '--btn-primary-border' => $btnPrimaryBorder,

            '--active-menu-bg' => $activeMenuBg,
            '--active-menu-border' => $activeMenuBorder,
            '--active-menu-text' => $activeMenuText,

            '--table-bg' => $tableBg,
            '--table-border' => $tableBorder,

            '--badge-bg' => $badgeBg,
            '--badge-text' => $badgeText,
            '--shadow-soft' => $secondaryLight
                ? '0 10px 28px rgba(15, 23, 42, 0.08)'
                : '0 12px 30px rgba(0, 0, 0, 0.28)',
            '--shadow-card' => $secondaryLight
                ? '0 16px 34px rgba(15, 23, 42, 0.10)'
                : '0 18px 38px rgba(0, 0, 0, 0.34)',
            '--shadow-elevated' => $secondaryLight
                ? '0 24px 52px rgba(15, 23, 42, 0.16)'
                : '0 28px 60px rgba(0, 0, 0, 0.42)',
            '--shadow-glow-brand' => '0 14px 34px '.strtolower($this->hexToRgba($primary, $secondaryLight ? 0.18 : 0.28)),

            '--sf-shell-bg' => $appShellBg,
            '--sf-layout-bg' => $appShellBg,
            '--sf-sidebar-bg' => $sidebarBg,
            '--sf-topbar-bg' => $topbarBg,
            '--sf-card-bg' => $cardBg,
            '--sf-card-soft-bg' => $cardSoftBg,
            '--sf-input-bg' => $inputBg,
            '--sf-text-main' => $textMain,
        ];
    }

    /**
     * @return array{0: int, 1: int, 2: int}|null
     */
    private function parseHexRgb(string $hex): ?array
    {
        $h = ltrim(strtoupper(trim($hex)), '#');
        if (strlen($h) !== 6 || ! ctype_xdigit($h)) {
            return null;
        }

        return [
            hexdec(substr($h, 0, 2)),
            hexdec(substr($h, 2, 2)),
            hexdec(substr($h, 4, 2)),
        ];
    }

    private function hexToRgba(string $hex, float $alpha): string
    {
        $rgb = $this->parseHexRgb($hex);

        if ($rgb === null) {
            return 'rgba(0, 0, 0, 0)';
        }

        $opacity = max(0, min(1, $alpha));

        return sprintf('rgba(%d, %d, %d, %.3F)', $rgb[0], $rgb[1], $rgb[2], $opacity);
    }
}
