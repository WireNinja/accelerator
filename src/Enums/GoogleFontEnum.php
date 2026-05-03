<?php

namespace WireNinja\Accelerator\Enums;

use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

enum GoogleFontEnum: string implements HasDescription, HasLabel
{
    // --- Sans-Serif ---
    case Poppins = 'Poppins';
    case PlusJakartaSans = 'Plus Jakarta Sans';
    case Inter = 'Inter';
    case Roboto = 'Roboto';
    case OpenSans = 'Open Sans';
    case Montserrat = 'Montserrat';
    case Lato = 'Lato';
    case Oswald = 'Oswald';
    case Nunito = 'Nunito';
    case Raleway = 'Raleway';
    case WorkSans = 'Work Sans';
    case Ubuntu = 'Ubuntu';

    // --- Serif ---
    case Merriweather = 'Merriweather';
    case PlayfairDisplay = 'Playfair Display';
    case Lora = 'Lora';
    case PTSerif = 'PT Serif';
    case EBGaramond = 'EB Garamond';
    case LibreBaskerville = 'Libre Baskerville';

    // --- Monospace ---
    case RobotoMono = 'Roboto Mono';
    case FiraCode = 'Fira Code';
    case JetBrainsMono = 'JetBrains Mono';
    case Inconsolata = 'Inconsolata';

    // --- Display & Handwriting ---
    case BebasNeue = 'Bebas Neue';
    case Pacifico = 'Pacifico';
    case Caveat = 'Caveat';
    case DancingScript = 'Dancing Script';
    case Righteous = 'Righteous';

    public function getLabel(): string
    {
        // Menggunakan value agar spasi pada nama font tetap dipertahankan di UI
        return $this->value;
    }

    public function getDescription(): string
    {
        return match ($this) {
            // Sans-Serif
            self::Poppins => 'Geometris, bulat, dan sangat populer untuk desain UI/UX saat ini.',
            self::PlusJakartaSans => 'Modern, bersih, terinspirasi dari tipografi kota Jakarta.',
            self::Inter => 'Dirancang khusus agar sangat terbaca di layar komputer dan ponsel.',
            self::Roboto => 'Font standar buatan Google, serbaguna dan bersih.',
            self::OpenSans => 'Sangat netral, ramah, dan mudah dibaca dalam ukuran kecil.',
            self::Montserrat => 'Nuansa geometris elegan ala poster desain abad ke-20.',
            self::Lato => 'Hangat, semi-membulat, namun tetap terlihat profesional.',
            self::Oswald => 'Bentuknya agak merapat (condensed), bagus untuk judul yang padat.',
            self::Nunito => 'Ujung hurufnya membulat, memberikan kesan bersahabat.',
            self::Raleway => 'Punya karakter elegan, terutama pada varian yang tipis.',
            self::WorkSans => 'Dioptimalkan untuk teks layar ukuran sedang hingga besar.',
            self::Ubuntu => 'Khas dengan potongan lekukan yang unik ala sistem operasi Ubuntu.',

            // Serif
            self::Merriweather => 'Dirancang khusus agar teks panjang sangat nyaman dibaca di layar digital.',
            self::PlayfairDisplay => 'Sangat mewah dan elegan, sering dipakai untuk majalah dan gaya hidup.',
            self::Lora => 'Memiliki lengkungan kontemporer, sangat cocok untuk cerita atau esai.',
            self::PTSerif => 'Klasik, tegas, dan berfungsi dengan baik di berbagai resolusi layar.',
            self::EBGaramond => 'Kebangkitan font tradisional Eropa yang timeless dan klasik.',
            self::LibreBaskerville => 'Sempurna untuk bacaan digital berkat proporsinya yang lebar.',

            // Monospace
            self::RobotoMono => 'Bersih dan tegas, cocok untuk data teknis atau koding.',
            self::FiraCode => 'Sangat populer di kalangan programmer karena fitur ligatures kodenya.',
            self::JetBrainsMono => 'Didesain khusus untuk mengurangi kelelahan mata saat membaca kode.',
            self::Inconsolata => 'Elegan, terinspirasi dari font terminal cetak resolusi tinggi.',

            // Display & Handwriting
            self::BebasNeue => 'Sangat tebal, tinggi, huruf kapital semua. Favorit untuk poster.',
            self::Pacifico => 'Gaya tulisan tangan kuas yang tebal ala budaya selancar Amerika.',
            self::Caveat => 'Menyerupai tulisan tangan kasual sehari-hari yang rapi.',
            self::DancingScript => 'Tulisan tangan sambung yang santai namun tetap meliuk elegan.',
            self::Righteous => 'Geometris dengan gaya art deco yang mulus.',
        };
    }
}
