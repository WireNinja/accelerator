<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Enums;

use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

enum LoginLayoutEnum: string implements HasDescription, HasLabel
{
    case LeftReveal = 'left_reveal';
    case RightReveal = 'right_reveal';
    case SplitScreen = 'split_screen';
    case SplitInset = 'split_inset';
    case Glassmorphism = 'glassmorphism';
    case MinimalStark = 'minimal_stark';

    public function getLabel(): string
    {
        return match ($this) {
            self::LeftReveal => 'Reveal (Form Kiri)',
            self::RightReveal => 'Reveal (Form Kanan)',
            self::SplitScreen => 'Split Screen (Belah Dua)',
            self::SplitInset => 'Split Inset (Gambar Membulat & Jarak)',
            self::Glassmorphism => 'Glassmorphism (Frosted Glass)',
            self::MinimalStark => 'Minimal Stark (Monokrom)',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::LeftReveal => 'Form di sebelah kiri, gradasi gelap menyingkap gambar latar di sebelah kanan.',
            self::RightReveal => 'Form di sebelah kanan, gradasi gelap menyingkap gambar latar di sebelah kiri.',
            self::SplitScreen => 'Layar terbagi dua secara simetris, form minimalis di kiri dan gambar penuh di kanan.',
            self::SplitInset => 'Layar terbagi dua secara simetris, gambar di sisi kanan memiliki gap/jarak dan sudut membulat modis.',
            self::Glassmorphism => 'Kartu login semi-transparan (frosted glass) melayang di tengah dengan latar belakang berpendar.',
            self::MinimalStark => 'Desain monokromatik ultra-bersih dengan dekorasi garis grid tipis, fokus pada tipografi.',
        };
    }
}
