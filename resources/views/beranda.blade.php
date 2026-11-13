<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="SIKVERSE, layanan Sekolah Islam Kebangsaan Fakultas Sains dan Teknologi UIN Imam Bonjol Padang.">
    <title>SIKVERSE | Sekolah Islam Kebangsaan</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&family=Konkhmer+Sleokchher&family=Kanchenjunga&family=Laila&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('Css/beranda.css') }}?v={{ filemtime(public_path('Css/beranda.css')) }}">
</head>

<body class="sik-home-page">
    <x-public-navigation active="beranda" />

    <main>
        <section class="hero" id="beranda" aria-labelledby="home-title">
            <div class="hero-content">
                <h1 id="home-title"><span class="hero-sik">SIK</span><span class="hero-verse">VERSE</span></h1>
                <h3>One Platform, Every School Connection</h3>
                <p>Platform informasi dan layanan<br>Sekolah Islam Kebangsaan</p>
            </div>
        </section>

        <section class="section about-section" id="tentang" aria-labelledby="about-title">
            <div class="section-title">
                <div class="line" aria-hidden="true"></div>
                <h2 id="about-title">Mengenal <span>SIKVERSE</span></h2>
            </div>
            <div class="about-layout">
                <div class="about-copy">
                    <p>Sekolah Islam Kebangsaan (SIK) merupakan program unggulan Fakultas Sains dan Teknologi UIN Imam Bonjol Padang. Program ini membantu mahasiswa memperdalam pemahaman keislaman sekaligus membangun karakter yang berlandaskan nilai keislaman, kebangsaan, dan kepedulian terhadap sesama.</p>
                    <p>SIKVERSE adalah sistem informasi layanan SIK yang menyatukan pengelolaan santri, kelompok, kegiatan, kehadiran, kelulusan, dan sertifikat dalam satu tempat.</p>
                    <p>Santri dapat melihat perkembangan kehadirannya, sementara instruktur dan admin mengelola kegiatan serta memantau peserta sesuai tanggung jawab masing-masing.</p>
                    <p>Laporan kehadiran dan kelulusan membantu pengelola mengevaluasi pelaksanaan program SIK secara teratur.</p>
                </div>
                <figure class="about-visual">
                    <img src="{{ asset('images/hero-sik.jpg') }}" alt="Santri mengikuti kegiatan Sekolah Islam Kebangsaan">
                </figure>
            </div>
        </section>

        <section class="section services" id="layanan" aria-labelledby="services-title">
            <div class="section-title">
                <div class="line" aria-hidden="true"></div>
                <h2 id="services-title">Layanan <span>SIKVERSE</span></h2>
            </div>

            <p class="services-intro">
                SIKVERSE menyediakan layanan untuk membantu santri dan pengelola program Sekolah Islam Kebangsaan.
            </p>

            <div class="service-grid">
                <article class="service-card"><div class="service-icon"><x-sik-icon name="students" /></div><h3>Manajemen Santri</h3><p>Kelola data santri, kelompok, dan anggota periode SIK.</p></article>
                <article class="service-card"><div class="service-icon"><x-sik-icon name="attendance" /></div><h3>Absensi Santri</h3><p>Catat dan pantau kehadiran santri pada setiap pertemuan.</p></article>
                <article class="service-card"><div class="service-icon"><x-sik-icon name="report" /></div><h3>Rekap Kehadiran</h3><p>Lihat persentase kehadiran, izin, sakit, dan alpha.</p></article>
                <article class="service-card"><div class="service-icon"><x-sik-icon name="certificate" /></div><h3>Layanan Sertifikat</h3><p>Kelola status kelulusan dan sertifikat peserta SIK.</p></article>
                <article class="service-card"><div class="service-icon"><x-sik-icon name="chart" /></div><h3>Laporan dan Evaluasi</h3><p>Pantau laporan kehadiran dan evaluasi kelulusan tahunan.</p></article>
            </div>
        </section>
    </main>

    <footer class="site-footer">
        <div class="site-footer-inner">
            <div class="site-footer-grid">
                <section class="footer-brand" aria-label="Tentang SIKVERSE">
                    <a class="footer-brand-lockup" href="#beranda">
                        <img src="{{ asset('images/logo-fst.png') }}" alt="Logo Fakultas Sains dan Teknologi UIN Imam Bonjol Padang">
                        <span>
                            <strong>SIKVERSE</strong>
                            <small>Fakultas Sains &amp; Teknologi</small>
                        </span>
                    </a>
                    <p>One Platform, Every School Connection</p>
                    <a class="footer-official-link" href="https://uinib.ac.id/" target="_blank" rel="noopener noreferrer">
                        Situs resmi UIN Imam Bonjol <x-sik-icon name="arrow-up-right" class="footer-link-icon" />
                    </a>
                </section>

                <nav class="footer-navigation" aria-label="Navigasi footer">
                    <h3>Navigasi</h3>
                    <a href="#beranda">Beranda</a>
                    <a href="#tentang">Tentang SIK</a>
                    <a href="#layanan">Layanan</a>
                    <a href="{{ route('login') }}">Masuk akun</a>
                </nav>

                <section class="footer-location" aria-label="Lokasi kampus">
                    <span class="footer-kicker">LOKASI KAMPUS SIK</span>
                    <h3>UIN Imam Bonjol Padang</h3>
                    <p>Fakultas Sains dan Teknologi<br>Kampus III Sungai Bangek, Kel. Balai Gadang,<br>Kec. Koto Tangah, Kota Padang, Sumatera Barat 25174</p>
                    <a class="footer-map-link" href="https://www.google.com/maps/search/?api=1&amp;query=Fakultas+Sains+dan+Teknologi+UIN+Imam+Bonjol+Padang+Kampus+III+Sungai+Bangek" target="_blank" rel="noopener noreferrer">
                        <x-sik-icon name="location" class="footer-location-icon" />
                        Lihat lokasi di peta <x-sik-icon name="arrow-up-right" class="footer-link-icon" />
                    </a>
                </section>
            </div>

            <div class="footer-bottom">
                <span>© {{ date('Y') }} SIKVERSE. Hak cipta dilindungi.</span>
                <span>Fakultas Sains &amp; Teknologi · UIN Imam Bonjol Padang</span>
            </div>
        </div>
    </footer>
    <script>
        (() => {
            const navbar = document.querySelector('.navbar');
            if (!navbar) return;
            const sectionLinks = [...navbar.querySelectorAll('.nav-menu .nav-link[href*="#"]')];
            const sections = sectionLinks
                .map((link) => document.querySelector(link.hash))
                .filter(Boolean);

            const updateNavbar = () => {
                navbar.classList.toggle('is-scrolled', window.scrollY > 24);

                const marker = window.scrollY + navbar.offsetHeight + window.innerHeight * .22;
                const activeSection = sections.reduce((active, section) => (
                    section.offsetTop <= marker ? section : active
                ), sections[0]);

                sectionLinks.forEach((link) => {
                    const isActive = link.hash === `#${activeSection?.id}`;
                    link.classList.toggle('active', isActive);

                    if (isActive) {
                        link.setAttribute('aria-current', 'location');
                    } else {
                        link.removeAttribute('aria-current');
                    }
                });
            };

            updateNavbar();
            window.addEventListener('scroll', updateNavbar, { passive: true });
            window.addEventListener('hashchange', updateNavbar);
            window.addEventListener('load', updateNavbar, { once: true });
        })();
    </script>
</body>
</html>
