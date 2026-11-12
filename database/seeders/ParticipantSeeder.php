<?php

namespace Database\Seeders;

use App\Models\ExcludedPerson;
use App\Models\Participant;
use Illuminate\Database\Seeder;

/**
 * Data awal dari file HTML lama. Hanya berjalan saat tabel kosong (tidak menimpa data yang sudah diubah panitia).
 * EMR (3 orang) dulu ikut sebagai 'imp' lewat flag INCLUDE_EMR=true;
 * sekarang langsung ditulis sebagai 'imp'. Hapus barisnya kalau tidak ikut.
 */
class ParticipantSeeder extends Seeder
{
    public function run(): void
    {
        // Data sekarang dikelola panitia lewat /panitia/peserta. Jangan timpa.
        if (Participant::query()->exists()) {
            $this->command?->warn('Peserta sudah ada: seeder dilewati. Kelola lewat /panitia/peserta.');

            return;
        }

        $data = [
            'pm' => [
                'Riski Nurcahyadi', 'Rahmat Hidayat', 'Muhammad Noer', 'Faizal Akbar Alfaridzi',
                'Asep Hasanudin', 'Yusep Teja Purnama', 'Muhammad Daffa Zaid Haritsah', 'Muhamad Fazri Irmansyah',
            ],
            'sm' => ['Erik Dani Apriadi', 'Dede Dian Komarudin'],
            'atm' => ['Joko Agus Rianto Ambarita'],
            'prg' => [
                'Daud Rifki Gunawan', 'Andika Muhammad Rezka', 'Arham Dadiamar', 'Yasin Jundi Habulloh',
                'Japar Sidik', 'Muhammad Reza', 'Sani Sanjaya', 'Muhammad Rezon Maulana',
                'Rohman Nurwahidin', 'Aldian Fallahakbar Nuriza', 'Bintang Rizky Sulistyo',
                'Nirwan Ramdani', 'Muhamad Abyan Ma’rup',
            ],
            'imp' => [
                'Budi Prasetio', 'Fazrie Alfikrie', 'Agvien Syauqi Fadhlurrahman', 'Sandi Rali',
                'Adhit Octavian', 'Yudi Pratama', 'Sabda Arya Putra', 'Muhamad Luthfy Noor',
                'Muhammad Fakhrijal Pratama', 'Rian Ibnu Rizal', 'Moch Safe’i Eka Gunawan',
                'Rico Miraj Irawan', 'Farhan Fathia Andriawan',
                // EMR
                'M. Reva Rusyda Andita', 'Mohamad Iqbal Indrayana', 'Martin Solehudin Mujib',
            ],
            'bb' => ['Irshad'],
        ];

        foreach ($data as $role => $names) {
            foreach ($names as $i => $name) {
                Participant::create(['name' => $name, 'role' => $role, 'sort' => $i]);
            }
        }

        $out = [
            'Driver & Backbone' => ['Arinda · Driver', 'Yudi · Driver', 'Pak Eko · Backbone'],
            'Panitia (tidak ikut diundi)' => [
                'Fadhil Rafi Fauzan', 'Rizal Naufal Ridwan', 'Muhamad Faris Rafi', 'Muhammad Yusuf Firdaus',
                'Anjastian Suryatul Akbar', 'Magie Adytia', 'Noufal Fadhillah Setiadi', 'Edgar David Deswan',
            ],
            'Tidak ikut gathering' => [
                'Wiki Saputra', 'Roby Harlen Setiadi', 'Arinda Bagaswara',
                'Muhammad Yahya Firdaus', 'Pak Eko', 'Mochamad Fikri Prayogi',
            ],
        ];

        $n = 0;
        foreach ($out as $group => $names) {
            foreach ($names as $name) {
                ExcludedPerson::create(['group' => $group, 'name' => $name, 'sort' => $n++]);
            }
        }
    }
}
