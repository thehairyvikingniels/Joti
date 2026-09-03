<?php
/**
 * install.php — Interactive Web Setup Wizard for Jotify
 */

$lockFile = __DIR__ . '/.installed';
if (file_exists($lockFile)) {
    header('Location: /login');
    exit();
}
?>
<!DOCTYPE html>
<html lang="nl" class="h-full bg-slate-900">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jotify Installatie Wizard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="icon" type="image/png" href="media/geusje_bevosd.png">
    <style>
        .step-active { border-color: #3b82f6; color: #3b82f6; }
        .step-done { border-color: #10b981; color: #10b981; }
        .step-pending { border-color: #475569; color: #94a3b8; }
    </style>
</head>
<body class="h-full text-slate-100 flex flex-col justify-between">

    <!-- Top Navigation Header -->
    <header class="bg-slate-800/80 backdrop-blur border-b border-slate-700 py-4 px-6 sticky top-0 z-50">
        <div class="max-w-5xl mx-auto flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <img src="media/geusje_bevosd.png" alt="Jotify Logo" class="h-9 w-auto" onerror="this.style.display='none'">
                <div>
                    <h1 class="text-xl font-bold tracking-tight text-white flex items-center gap-2">
                        <span>Jotify</span>
                        <span class="text-xs bg-blue-500/20 text-blue-400 border border-blue-500/30 px-2 py-0.5 rounded-full font-mono font-normal">Auto-Installer</span>
                    </h1>
                    <p class="text-xs text-slate-400">Tactical Foxhunt Dashboard Setup</p>
                </div>
            </div>
            <div class="text-xs text-slate-400 font-mono flex items-center gap-2 bg-slate-900/60 px-3 py-1.5 rounded-lg border border-slate-700">
                <i class="fa-solid fa-server text-blue-400"></i>
                <span>PHP <?= PHP_VERSION ?></span>
            </div>
        </div>
    </header>

    <!-- Main Content Container -->
    <main class="flex-grow max-w-5xl w-full mx-auto px-4 py-8">

        <!-- Step Indicator Bar -->
        <div class="mb-8">
            <div class="grid grid-cols-2 md:grid-cols-6 gap-2 text-center text-xs font-medium">
                <div id="step-tab-1" class="step-active border-b-2 pb-2 transition-all">
                    <span class="block text-sm font-bold">1</span> Systeemcontrole
                </div>
                <div id="step-tab-2" class="step-pending border-b-2 pb-2 transition-all">
                    <span class="block text-sm font-bold">2</span> Database
                </div>
                <div id="step-tab-3" class="step-pending border-b-2 pb-2 transition-all">
                    <span class="block text-sm font-bold">3</span> Beheerder
                </div>
                <div id="step-tab-4" class="step-pending border-b-2 pb-2 transition-all">
                    <span class="block text-sm font-bold">4</span> Spel & APIs
                </div>
                <div id="step-tab-5" class="step-pending border-b-2 pb-2 transition-all">
                    <span class="block text-sm font-bold">5</span> Achtergrondtaken
                </div>
                <div id="step-tab-6" class="step-pending border-b-2 pb-2 transition-all">
                    <span class="block text-sm font-bold">6</span> Voltooien
                </div>
            </div>
        </div>

        <!-- Notification Banner Container -->
        <div id="alert-box" class="hidden mb-6 p-4 rounded-xl text-sm border flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <i id="alert-icon" class="fa-solid fa-circle-info text-lg"></i>
                <span id="alert-msg"></span>
            </div>
            <button onclick="hideAlert()" class="text-slate-400 hover:text-white"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <!-- ================================================================= -->
        <!-- STEP 1: System Requirements Check                                 -->
        <!-- ================================================================= -->
        <div id="step-1" class="step-card bg-slate-800 border border-slate-700 rounded-2xl p-6 shadow-xl">
            <div class="flex items-center justify-between pb-4 border-b border-slate-700/80 mb-6">
                <div>
                    <h2 class="text-lg font-bold text-white flex items-center gap-2">
                        <i class="fa-solid fa-microchip text-blue-400"></i> Systeem- & Serververeisten
                    </h2>
                    <p class="text-sm text-slate-400">Controleer of alle vereiste PHP-extensies en maprechten beschikbaar zijn.</p>
                </div>
                <button onclick="runSystemChecks()" class="text-xs bg-slate-700 hover:bg-slate-600 text-slate-200 px-3 py-1.5 rounded-lg border border-slate-600 transition flex items-center gap-1.5">
                    <i class="fa-solid fa-rotate text-blue-400"></i> Opnieuw controleren
                </button>
            </div>

            <div id="syscheck-loading" class="text-center py-12">
                <i class="fa-solid fa-circle-notch fa-spin text-4xl text-blue-500 mb-3"></i>
                <p class="text-slate-400 text-sm">Systeemvereisten controleren...</p>
            </div>

            <div id="syscheck-results" class="hidden space-y-6">
                <!-- PHP Extensions Grid -->
                <div>
                    <h3 class="text-sm font-semibold text-slate-300 mb-3 uppercase tracking-wider text-xs">PHP Extensies</h3>
                    <div id="extensions-grid" class="grid grid-cols-1 md:grid-cols-2 gap-2.5"></div>
                </div>

                <!-- Directories Grid -->
                <div>
                    <h3 class="text-sm font-semibold text-slate-300 mb-3 uppercase tracking-wider text-xs">Maprechten & Opslag</h3>
                    <div id="directories-grid" class="grid grid-cols-1 md:grid-cols-2 gap-2.5"></div>
                </div>
            </div>

            <div class="mt-8 pt-4 border-t border-slate-700 flex justify-end">
                <button id="btn-step-1" onclick="goToStep(2)" disabled class="bg-blue-600 hover:bg-blue-500 disabled:opacity-50 disabled:cursor-not-allowed text-white font-medium px-6 py-2.5 rounded-xl shadow-lg shadow-blue-500/20 transition flex items-center gap-2">
                    <span>Volgende: Database</span>
                    <i class="fa-solid fa-arrow-right"></i>
                </button>
            </div>
        </div>

        <!-- ================================================================= -->
        <!-- STEP 2: Database Setup                                            -->
        <!-- ================================================================= -->
        <div id="step-2" class="step-card hidden bg-slate-800 border border-slate-700 rounded-2xl p-6 shadow-xl">
            <div class="pb-4 border-b border-slate-700/80 mb-6">
                <h2 class="text-lg font-bold text-white flex items-center gap-2">
                    <i class="fa-solid fa-database text-purple-400"></i> MariaDB / MySQL Database Configuratie
                </h2>
                <p class="text-sm text-slate-400">Maak automatisch de database, gebruiker en tabellen aan.</p>
            </div>

            <form id="form-database" class="space-y-6" onsubmit="submitDatabase(event)">
                <!-- Database Mode Selector -->
                <div class="bg-slate-900/80 p-4 rounded-xl border border-slate-700 space-y-3">
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider">Installatiemodus Database</label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <label class="relative flex items-center p-3 rounded-lg border border-blue-500/50 bg-blue-500/10 cursor-pointer transition hover:bg-blue-500/20" id="label_mode_create">
                            <input type="radio" name="db_mode" value="create_new" checked onchange="toggleDbMode(this.value)" class="text-blue-500 focus:ring-blue-400">
                            <div class="ml-3">
                                <span class="block text-sm font-medium text-white">Nieuwe Database Aanmaken</span>
                                <span class="block text-xs text-slate-400">Maakt database en gebruiker aan (vereist root)</span>
                            </div>
                        </label>
                        <label class="relative flex items-center p-3 rounded-lg border border-slate-700 bg-slate-800/60 cursor-pointer transition hover:bg-slate-800" id="label_mode_existing">
                            <input type="radio" name="db_mode" value="use_existing" onchange="toggleDbMode(this.value)" class="text-blue-500 focus:ring-blue-400">
                            <div class="ml-3">
                                <span class="block text-sm font-medium text-white">Bestaande Database Gebruiken</span>
                                <span class="block text-xs text-slate-400">Verbindt direct met opgegeven gebruiker (geen root)</span>
                            </div>
                        </label>
                    </div>

                    <!-- Optional Table Wipe for Existing DB -->
                    <div id="card-existing-options" class="hidden mt-3 bg-amber-500/10 border border-amber-500/30 p-3 rounded-lg flex items-start gap-2.5 text-xs text-amber-200">
                        <input type="checkbox" id="drop_existing" class="mt-0.5 rounded text-amber-500 focus:ring-amber-400">
                        <label for="drop_existing" class="cursor-pointer select-none">
                            <strong>Bestaande tabellen overschrijven / legen:</strong> Verwijder eventuele bestaande tabellen in deze database voor een schone start. Laat uitgeschakeld om bestaande tabellen te behouden.
                        </label>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    
                    <!-- App Database Details -->
                    <div class="space-y-4 bg-slate-900/50 p-4 rounded-xl border border-slate-700/60">
                        <h3 class="text-sm font-semibold text-blue-400 flex items-center gap-2">
                            <i class="fa-solid fa-table"></i> Jotify Applicatie Database
                        </h3>
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Database Host</label>
                            <input type="text" id="db_host" value="localhost" required class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500 font-mono">
                        </div>
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Database Naam</label>
                            <input type="text" id="db_name" value="jotihunt" required class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500 font-mono">
                        </div>
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Database Gebruikersnaam</label>
                            <input type="text" id="db_user" value="jotify" required class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500 font-mono">
                        </div>
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Database Wachtwoord</label>
                            <div class="relative">
                                <input type="text" id="db_pass" required class="w-full bg-slate-800 border border-slate-600 rounded-lg pl-3 pr-10 py-2 text-sm text-white font-mono focus:outline-none focus:border-blue-500">
                                <button type="button" onclick="generateRandomDbPass()" class="absolute right-2 top-2 text-xs text-slate-400 hover:text-white" title="Genereer veilig wachtwoord">
                                    <i class="fa-solid fa-dice"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Root DB Credentials (only shown in create_new mode) -->
                    <div id="card-root-credentials" class="space-y-4 bg-slate-900/50 p-4 rounded-xl border border-slate-700/60">
                        <h3 class="text-sm font-semibold text-purple-400 flex items-center gap-2">
                            <i class="fa-solid fa-key"></i> Database Beheerdersrechten
                        </h3>
                        <p class="text-xs text-slate-400">
                            Nodig om de database en gebruiker eenmalig aan te maken. Deze gegevens worden niet opgeslagen.
                        </p>
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Beheerder Gebruikersnaam</label>
                            <input type="text" id="root_user" value="root" required class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-purple-500 font-mono">
                        </div>
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Beheerder Wachtwoord (Leeg laten bij socket auth)</label>
                            <input type="password" id="root_pass" placeholder="Optioneel (Debian root gebruikt standaard socket)" class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-purple-500">
                        </div>
                        <div class="pt-4 text-xs text-slate-500">
                            <i class="fa-solid fa-circle-info text-blue-400 mr-1"></i> De installer importeert automatisch het complete tabellenschema uit <code class="text-slate-300">DB/createDB.sql</code> en schrijft de configuratie naar <code class="text-slate-300">dblogin.php</code>.
                        </div>
                    </div>

                </div>

                <div class="mt-8 pt-4 border-t border-slate-700 flex justify-between">
                    <button type="button" onclick="goToStep(1)" class="bg-slate-700 hover:bg-slate-600 text-slate-200 px-5 py-2.5 rounded-xl transition">
                        <i class="fa-solid fa-arrow-left mr-1"></i> Vorige
                    </button>
                    <button type="submit" id="btn-submit-db" class="bg-blue-600 hover:bg-blue-500 text-white font-medium px-6 py-2.5 rounded-xl shadow-lg shadow-blue-500/20 transition flex items-center gap-2">
                        <span id="db-btn-text">Database Installeren & Schema Importeren</span>
                        <i class="fa-solid fa-database"></i>
                    </button>
                </div>
            </form>
        </div>

        <!-- ================================================================= -->
        <!-- STEP 3: Admin Account Creation                                    -->
        <!-- ================================================================= -->
        <div id="step-3" class="step-card hidden bg-slate-800 border border-slate-700 rounded-2xl p-6 shadow-xl">
            <div class="pb-4 border-b border-slate-700/80 mb-6">
                <h2 class="text-lg font-bold text-white flex items-center gap-2">
                    <i class="fa-solid fa-user-shield text-emerald-400"></i> Eerste Superadmin Beheerderaccount
                </h2>
                <p class="text-sm text-slate-400">Maak het primaire beheerdersaccount aan met volledige rechten (Privilege 3).</p>
            </div>

            <form id="form-admin" class="space-y-6" onsubmit="submitAdmin(event)">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs text-slate-400 mb-1">Voornaam</label>
                        <input type="text" id="admin_voornaam" required placeholder="Bijv. Jan" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs text-slate-400 mb-1">Achternaam</label>
                        <input type="text" id="admin_achternaam" required placeholder="Bijv. Vossen" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs text-slate-400 mb-1">E-mailadres</label>
                        <input type="email" id="admin_email" required placeholder="beheerder@scouting.nl" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs text-slate-400 mb-1">Telefoonnummer (Optioneel)</label>
                        <input type="text" id="admin_phone" value="0600000000" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs text-slate-400 mb-1">Gebruikersnaam</label>
                        <input type="text" id="admin_username" required placeholder="Admin" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs text-slate-400 mb-1">Wachtwoord (Minimaal 8 tekens)</label>
                        <input type="password" id="admin_password" required minlength="8" placeholder="••••••••••••" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500">
                    </div>
                </div>

                <div class="mt-8 pt-4 border-t border-slate-700 flex justify-between">
                    <button type="button" onclick="goToStep(2)" class="bg-slate-700 hover:bg-slate-600 text-slate-200 px-5 py-2.5 rounded-xl transition">
                        <i class="fa-solid fa-arrow-left mr-1"></i> Vorige
                    </button>
                    <button type="submit" id="btn-submit-admin" class="bg-blue-600 hover:bg-blue-500 text-white font-medium px-6 py-2.5 rounded-xl shadow-lg shadow-blue-500/20 transition flex items-center gap-2">
                        <span>Beheerder Aanmaken</span>
                        <i class="fa-solid fa-arrow-right"></i>
                    </button>
                </div>
            </form>
        </div>

        <!-- ================================================================= -->
        <!-- STEP 4: Jotihunt & Site Settings (with Live Test Buttons)         -->
        <!-- ================================================================= -->
        <div id="step-4" class="step-card hidden bg-slate-800 border border-slate-700 rounded-2xl p-6 shadow-xl">
            <div class="pb-4 border-b border-slate-700/80 mb-6">
                <h2 class="text-lg font-bold text-white flex items-center gap-2">
                    <i class="fa-solid fa-sliders text-amber-400"></i> Spel- & API Instellingen
                </h2>
                <p class="text-sm text-slate-400">Configureer Scoutinggroep gegevens, API sleutels en speldata.</p>
            </div>

            <form id="form-settings" class="space-y-6" onsubmit="submitSettings(event)">
                
                <!-- Card 1: Jotihunt.nl Login & Auto-Prefill -->
                <div class="bg-slate-900/60 p-4 rounded-xl border border-slate-700">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-sm font-semibold text-amber-400 flex items-center gap-2">
                            <i class="fa-solid fa-cloud-arrow-down"></i> Jotihunt.nl Portal Koppeling (Optioneel)
                        </h3>
                        <span class="text-xs text-slate-400">Automatisch gegevens invullen</span>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 items-end">
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Jotihunt.nl Gebruikersnaam / E-mail</label>
                            <input type="text" id="joti_user" placeholder="groep@scouting.nl" class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-amber-500">
                        </div>
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Jotihunt.nl Wachtwoord</label>
                            <input type="password" id="joti_pass" placeholder="••••••••" class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-amber-500">
                        </div>
                        <div>
                            <button type="button" onclick="scrapeJotihuntPortal()" id="btn-scrape" class="w-full bg-amber-600 hover:bg-amber-500 text-white px-3 py-2 rounded-lg text-sm font-medium transition flex items-center justify-center gap-2">
                                <i class="fa-solid fa-bolt"></i>
                                <span>Gegevens Ophalen</span>
                            </button>
                        </div>
                    </div>
                    <div id="scrape-status" class="hidden text-xs mt-2 p-2 rounded bg-slate-800/80 border border-slate-700"></div>
                </div>

                <!-- Card 2: Group Identity & Logo -->
                <div class="bg-slate-900/60 p-4 rounded-xl border border-slate-700 space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-blue-400 flex items-center gap-2">
                            <i class="fa-solid fa-campground"></i> Scoutinggroep Identiteit
                        </h3>
                        <button type="button" onclick="fetchJotihuntGroups()" id="btn-fetch-groups" class="bg-blue-600/80 hover:bg-blue-600 text-white text-xs px-3 py-1.5 rounded-lg transition flex items-center gap-1.5">
                            <i class="fa-solid fa-cloud-arrow-down"></i>
                            <span>Haal Groepen Op van Jotihunt</span>
                        </button>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div class="md:col-span-2">
                            <label class="block text-xs text-slate-400 mb-1">Selecteer Scoutinggroep</label>
                            <select id="group_select" onchange="onGroupSelect(this)" class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500">
                                <option value="1" data-name="Mijn Scoutinggroep" data-city="Arnhem">1 - Mijn Scoutinggroep (Standaard / Handmatig)</option>
                            </select>
                            <div id="group-fetch-status" class="text-xs text-slate-400 mt-1 hidden"></div>
                        </div>
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Groeps-ID (Nummer)</label>
                            <input type="number" id="group_id" value="1" class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500 font-mono">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Groepsnaam</label>
                            <input type="text" id="group_name" value="Mijn Scoutinggroep" placeholder="Bijv. Scouting De Vossen" class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500">
                        </div>
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Website URL</label>
                            <input type="url" id="group_url" value="https://scouting.nl" class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500">
                        </div>
                    </div>

                    <!-- Logo Upload & Selector -->
                    <div class="pt-2 border-t border-slate-800">
                        <label class="block text-xs text-slate-400 mb-2">Groepslogo</label>
                        <div class="flex flex-wrap items-center gap-4">
                            <img id="logo_preview" src="media/geusje_bevosd.png" alt="Logo preview" class="w-14 h-14 object-contain bg-slate-800 rounded-xl p-1 border border-slate-600 shadow-inner">
                            <div class="space-y-1.5 flex-grow">
                                <div class="flex items-center gap-2">
                                    <input type="file" id="group_logo_file" accept="image/png,image/jpeg,image/svg+xml,image/webp" class="hidden" onchange="uploadLogoFile(event)">
                                    <button type="button" onclick="document.getElementById('group_logo_file').click()" id="btn-upload-logo" class="bg-emerald-600 hover:bg-emerald-500 text-white px-3 py-1.5 rounded-lg text-xs font-medium transition flex items-center gap-1.5">
                                        <i class="fa-solid fa-upload"></i>
                                        <span>Upload Eigen Logo</span>
                                    </button>
                                    <span class="text-xs text-slate-400">of voer een pad / URL in:</span>
                                </div>
                                <input type="text" id="group_logo_large_url" value="media/geusje_bevosd.png" onchange="document.getElementById('logo_preview').src = this.value" class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-1.5 text-xs text-white focus:outline-none focus:border-blue-500 font-mono">
                                <div id="logo-upload-status" class="text-xs hidden"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card 3: API Keys with Test Buttons -->
                <div class="bg-slate-900/60 p-4 rounded-xl border border-slate-700 space-y-4">
                    <h3 class="text-sm font-semibold text-emerald-400 flex items-center gap-2">
                        <i class="fa-solid fa-key"></i> Externe API Sleutels & Tokens
                    </h3>
                    
                    <!-- Mapbox Key -->
                    <div>
                        <label class="block text-xs text-slate-400 mb-1">Mapbox Public Access Token (voor Kaarten)</label>
                        <div class="flex gap-2">
                            <input type="text" id="api_key_mapbox" placeholder="pk.eyJ1I..." class="flex-grow bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-sm text-white font-mono text-xs">
                            <button type="button" onclick="testApiKey('mapbox')" class="bg-slate-700 hover:bg-slate-600 text-xs px-3 py-2 rounded-lg border border-slate-600 whitespace-nowrap">
                                <i class="fa-solid fa-vial mr-1"></i> Test Token
                            </button>
                        </div>
                        <div id="test-status-mapbox" class="text-xs mt-1 hidden"></div>
                    </div>

                    <!-- Telegram Bot Token -->
                    <div>
                        <label class="block text-xs text-slate-400 mb-1">Telegram Bot Token (van @BotFather)</label>
                        <div class="flex gap-2">
                            <input type="text" id="telegram_bot_token" placeholder="123456789:ABCdef..." class="flex-grow bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-sm text-white font-mono text-xs">
                            <button type="button" onclick="testApiKey('telegram')" class="bg-slate-700 hover:bg-slate-600 text-xs px-3 py-2 rounded-lg border border-slate-600 whitespace-nowrap">
                                <i class="fa-solid fa-vial mr-1"></i> Test Bot
                            </button>
                        </div>
                        <div id="test-status-telegram" class="text-xs mt-1 hidden"></div>
                    </div>

                    <!-- Telegram MTProto App Info (my.telegram.org) -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 pt-2">
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Telegram App API ID (my.telegram.org)</label>
                            <input type="text" id="telegram_api_id" value="0" class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-sm text-white font-mono">
                        </div>
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Telegram App API Hash (my.telegram.org)</label>
                            <input type="text" id="telegram_api_hash" value="placeholder_api_hash" class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-sm text-white font-mono text-xs">
                        </div>
                    </div>
                </div>

                <!-- Card 4: Game Dates & Fox Configuration -->
                <div class="bg-slate-900/60 p-4 rounded-xl border border-slate-700 space-y-3">
                    <h3 class="text-sm font-semibold text-purple-400 flex items-center gap-2">
                        <i class="fa-solid fa-calendar-days"></i> Speldata & Vossen
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Startdatum & Tijd</label>
                            <input type="text" id="game_startdate" value="2026-10-17T10:00:00+02:00" class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-sm text-white font-mono">
                        </div>
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Einddatum & Tijd</label>
                            <input type="text" id="game_enddate" value="2026-10-18T12:00:00+02:00" class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-sm text-white font-mono">
                        </div>
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Vossenwissel Begin</label>
                            <input type="text" id="foxexchange_startdate" value="2026-10-17T22:45:00+02:00" class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-sm text-white font-mono">
                        </div>
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Vossenwissel Einde</label>
                            <input type="text" id="foxexchange_enddate" value="2026-10-17T23:15:00+02:00" class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-sm text-white font-mono">
                        </div>
                    </div>
                    <div class="pt-2">
                        <label class="block text-xs text-slate-400 mb-1">Vossennamen (Door komma gescheiden)</label>
                        <input type="text" id="fox_names" value="Alpha,Bravo,Charlie,Delta,Echo,Foxtrot,Golf,Hotel,Oscar" class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-sm text-white font-mono text-xs">
                    </div>
                </div>

                <div class="mt-8 pt-4 border-t border-slate-700 flex justify-between">
                    <button type="button" onclick="goToStep(3)" class="bg-slate-700 hover:bg-slate-600 text-slate-200 px-5 py-2.5 rounded-xl transition">
                        <i class="fa-solid fa-arrow-left mr-1"></i> Vorige
                    </button>
                    <button type="submit" id="btn-submit-settings" class="bg-blue-600 hover:bg-blue-500 text-white font-medium px-6 py-2.5 rounded-xl shadow-lg shadow-blue-500/20 transition flex items-center gap-2">
                        <span>Instellingen Opslaan</span>
                        <i class="fa-solid fa-arrow-right"></i>
                    </button>
                </div>
            </form>
        </div>

        <!-- ================================================================= -->
        <!-- STEP 5: Background Tasks & Crontab Setup                          -->
        <!-- ================================================================= -->
        <div id="step-5" class="step-card hidden bg-slate-800 border border-slate-700 rounded-2xl p-6 shadow-xl">
            <div class="pb-4 border-b border-slate-700/80 mb-6">
                <h2 class="text-lg font-bold text-white flex items-center gap-2">
                    <i class="fa-solid fa-clock-rotate-left text-cyan-400"></i> Achtergrondtaken & Crontab
                </h2>
                <p class="text-sm text-slate-400">Stel de periodieke synchronisatie in voor vossenstatussen, pushberichten en scores.</p>
            </div>

            <form id="form-cron" class="space-y-6" onsubmit="submitCrontab(event)">
                <div class="bg-slate-900/60 p-5 rounded-xl border border-slate-700 space-y-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-semibold text-white">Automatische Achtergrondtaken Inschakelen</h3>
                            <p class="text-xs text-slate-400">Installeert de crontab runner voor automatische data synchronisatie.</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" id="enable_cron" checked class="sr-only peer">
                            <div class="w-11 h-6 bg-slate-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                        </label>
                    </div>

                    <div class="pt-4 border-t border-slate-700/60">
                        <label class="block text-xs text-slate-300 font-semibold mb-2">Master Cron Uitvoeringsinterval: <span id="interval-display" class="text-blue-400 font-bold">20</span> seconden</label>
                        <input type="range" id="cron_interval" min="10" max="60" step="5" value="20" oninput="document.getElementById('interval-display').textContent = this.value" class="w-full h-2 bg-slate-700 rounded-lg appearance-none cursor-pointer accent-blue-500">
                        <div class="flex justify-between text-[11px] text-slate-500 mt-1">
                            <span>10s (Heel snel)</span>
                            <span>20s (Aanbevolen standaard)</span>
                            <span>60s (Standaard minuut)</span>
                        </div>
                    </div>
                </div>

                <div class="bg-slate-900/30 p-4 rounded-xl border border-slate-700/40 text-xs text-slate-400 space-y-1">
                    <p class="font-semibold text-slate-300">Standaard geactiveerde taken:</p>
                    <ul class="list-disc list-inside space-y-0.5">
                        <li><strong class="text-slate-300">areas.php</strong>: Vossenstatussen synchroniseren met Jotihunt.nl (30s)</li>
                        <li><strong class="text-slate-300">articles.php</strong>: Nieuws, hints & opdrachten ophalen (60s)</li>
                        <li><strong class="text-slate-300">notifications.php</strong>: Pushnotificaties & Telegram verzending (40s)</li>
                        <li><strong class="text-slate-300">subscriptions.php</strong>: Deelnemende scoutinggroepen synchroniseren (300s)</li>
                        <li><strong class="text-slate-300">jotiPortal</strong>: Automatisch punten & registratiecode scrapen (180s)</li>
                    </ul>
                </div>

                <div class="mt-8 pt-4 border-t border-slate-700 flex justify-between">
                    <button type="button" onclick="goToStep(4)" class="bg-slate-700 hover:bg-slate-600 text-slate-200 px-5 py-2.5 rounded-xl transition">
                        <i class="fa-solid fa-arrow-left mr-1"></i> Vorige
                    </button>
                    <button type="submit" id="btn-submit-cron" class="bg-blue-600 hover:bg-blue-500 text-white font-medium px-6 py-2.5 rounded-xl shadow-lg shadow-blue-500/20 transition flex items-center gap-2">
                        <span>Achtergrondtaken Opslaan</span>
                        <i class="fa-solid fa-arrow-right"></i>
                    </button>
                </div>
            </form>
        </div>

        <!-- ================================================================= -->
        <!-- STEP 6: Completion & Lockdown                                     -->
        <!-- ================================================================= -->
        <div id="step-6" class="step-card hidden bg-slate-800 border border-slate-700 rounded-2xl p-6 shadow-xl text-center">
            <div class="w-16 h-16 bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 rounded-2xl flex items-center justify-center text-3xl mx-auto mb-4 shadow-lg shadow-emerald-500/10">
                <i class="fa-solid fa-check"></i>
            </div>

            <h2 class="text-2xl font-bold text-white mb-2">Jotify is Succesvol Geïnstalleerd!</h2>
            <p class="text-slate-400 text-sm max-w-lg mx-auto mb-8">
                Je systeem is volledig geconfigureerd met de database, beheerder, API instellingen en achtergrondtaken.
            </p>

            <div class="bg-slate-900/60 p-5 rounded-2xl border border-slate-700 text-left max-w-lg mx-auto mb-8 space-y-3 text-xs">
                <h3 class="text-sm font-semibold text-slate-200 flex items-center gap-2">
                    <i class="fa-solid fa-clipboard-list text-blue-400"></i> Volgende Aanbevolen Stappen:
                </h3>
                <div class="space-y-2 text-slate-400">
                    <div class="flex items-start gap-2">
                        <i class="fa-solid fa-circle-check text-emerald-400 mt-0.5"></i>
                        <span><strong>Inloggen:</strong> Gebruik het zojuist aangemaakte Superadmin account.</span>
                    </div>
                    <div class="flex items-start gap-2">
                        <i class="fa-solid fa-circle-check text-emerald-400 mt-0.5"></i>
                        <span><strong>Telegram Bot:</strong> Registreer de webhook in <em class="text-slate-300">Admin &rarr; Telegram</em> voor live locatie streaming.</span>
                    </div>
                    <div class="flex items-start gap-2">
                        <i class="fa-solid fa-circle-check text-emerald-400 mt-0.5"></i>
                        <span><strong>MTProto Daemon:</strong> Koppel optioneel de background listener voor instant alerts (<em class="text-slate-300">services/setup_session.py</em>).</span>
                    </div>
                </div>
            </div>

            <button onclick="finalizeInstallation()" id="btn-finalize" class="bg-emerald-600 hover:bg-emerald-500 text-white font-bold px-8 py-3.5 rounded-xl shadow-xl shadow-emerald-600/30 transition text-base inline-flex items-center gap-2">
                <span>Start Jotify</span>
                <i class="fa-solid fa-rocket"></i>
            </button>
        </div>

    </main>

    <!-- Footer -->
    <footer class="text-center py-4 text-xs text-slate-500 border-t border-slate-800">
        Jotify Tactical Hunting Platform &bull; <?= date('Y') ?>
    </footer>

    <script src="js/install.js"></script>
</body>
</html>
