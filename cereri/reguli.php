 <?php
session_start();
require '../includes/db.php'; 
$role = $_SESSION['user_role'] ?? 'cititor';  
$user_name = $_SESSION['user_name'] ?? 'Vizitator';
?>

<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <title>Reguli și Termeni de Utilizare | PANORAMA</title>
    <link rel="stylesheet" href="../style.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Playfair+Display:wght@400;700&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
            color: #1A1D1F;
            margin: 0;
            padding: 0;
            background: url('../ref/rules.jpg') no-repeat center;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* HEADER */
        .classic-header {
            text-align: center;
            padding-top: 40px;
            background: #fff;
            margin-bottom: 40px;
        }
        .header-subtitle {
            font-family: 'Inter', sans-serif;
            font-size: 0.75rem;
            letter-spacing: 3px;
            text-transform: uppercase;
            color: #333;
            margin-bottom: 10px;
        }
        .header-title {
            font-family: 'Playfair Display', serif;
            font-size: 5rem;
            font-weight: 700;
            color: #000;
            margin: 0 0 30px 0;
            line-height: 1;
        }
        .nav-strip {
            border-top: 1px solid #000;
            border-bottom: 1px solid #000;
            display: flex;
            justify-content: center;
            align-items: stretch;
            max-width: 100%; 
        }
        .nav-strip a {
            text-decoration: none;
            color: #333;
            font-family: 'Inter', sans-serif;
            font-size: 0.9rem;
            text-transform: uppercase;
            padding: 15px 40px;
            border-right: 1px solid #eee;
            transition: background 0.2s, color 0.2s;
        }
        .nav-strip a:first-child { border-left: 1px solid #eee; }
        .nav-strip a:hover { background-color: #f9f9f9; color: #000; }
        .nav-strip a.logout-link { color: #d9534f; }
        .nav-strip a[style*="font-weight: 700"] { font-weight: 700; color: #000; background-color: #f5f5f5; }

        .page-wrapper {
            max-width: 800px; 
            width: 100%;
            margin: 0 auto 50px auto;
            background: #fff; 
            padding: 60px 40px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.05);
            flex-grow: 1;
        }

        /* Titlu */
        .page-wrapper h1 {
            font-family: 'Playfair Display', serif;
            font-size: 3.5rem;
            font-weight: 700;
            color: #000;
            margin-bottom: 10px;
            line-height: 1.1;
            border-bottom: 2px solid #000; 
            padding-bottom: 15px;
        }
        
        .page-wrapper h2 {
            font-family: 'Playfair Display', serif;
            font-size: 1.8rem;
            font-weight: 700;
            margin-top: 40px;
            margin-bottom: 15px;
            color: #000;
        }
        
        /* Citat/Notă de Avertizare  */
        .editorial-quote {
            border-left: 5px solid #000; 
            padding: 15px 20px;
            margin: 30px 0;
            background: #f8f8f8; 
            font-style: italic;
            color: #333;
            font-size: 1.1rem;
            line-height: 1.6;
        }

        /* Conținutul listelor */
        .page-wrapper ol {
            padding-left: 25px;
            margin-bottom: 30px;
        }
        .page-wrapper ol li {
            margin-bottom: 10px;
            line-height: 1.6;
            color: #333;
        }

        /* Separator */
        .page-wrapper hr {
            margin: 40px 0;
            border: 0;
            border-top: 1px solid #ccc;
        }

        /* --- FOOTER --- */
        .footer { 
            margin-top: auto;
            padding: 20px;
            text-align: center;
            background: #fff;
            border-top: 1px solid #000;
            flex-shrink: 0;
            font-size: 0.85rem;
            color: #555;
        }
        .footer .social-links a {
            color: #000;
            text-decoration: none;
            margin: 0 5px;
            font-weight: 600;
        }
        .footer .social-links a:hover {
            text-decoration: underline;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .header-title { font-size: 3rem; }
            .nav-strip { flex-direction: column; border-bottom: none; }
            .nav-strip a { border-right: none; border-bottom: 1px solid #eee; padding: 10px; }
            .page-wrapper { padding: 30px 15px; margin-bottom: 30px; }
            .page-wrapper h1 { font-size: 2.5rem; }
        }
    </style>
</head>
<body>

<header class="classic-header">
    <div class="header-subtitle">TOTUL ESTE PERSONAL. INCLUSIV ACEASTĂ REVISTĂ.</div>
    <h1 class="header-title">PANORAMA</h1>
    
    <div class="nav-strip">
        <a href="../index.php">Acasa</a>

        <?php if ($role === 'admin' || $role === 'autor'): ?>
            <a href="../articole/articol_add.php">Atelier</a>
        <?php endif; ?>

        <?php if ($role === 'cititor' && isset($_SESSION['user_id'])): ?>
            <a href="cititor_autor.php">Echipă</a>
        <?php endif; ?>

        <a href="reguli.php" style="font-weight: 700;">Reguli</a>
        
        <?php if (isset($_SESSION['user_id'])): ?>
            <a href="../auth/contul_meu.php">Contul meu</a>
        <?php endif; ?>

        <?php if ($role === 'admin'): ?>
            <a href="cereri-admin.php">Cereri</a>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['user_id'])): ?>
             <a href="../auth/logout.php" class="logout-link">Logout</a>
        <?php endif; ?>
    </div>
</header>

<div class="page-wrapper">
    <h1 style="margin-top: 0;">Reguli și Termeni de Utilizare</h1>
    
    <p style="font-size: 1.1rem; color: #555;">
        Bine ați venit la secțiunea de reguli. Pentru a menține o comunitate bazată pe respect și conținut de calitate, vă rugăm să parcurgeți cu atenție termenii noștri de utilizare.
    </p>

    <div class="editorial-quote">
        "Credem în libertatea de exprimare, dar nu tolerăm discursul instigator la ură, violența sau discriminarea. Conținutul publicat trebuie să respecte întotdeauna demnitatea umană."
    </div>

    <h2>1. Utilizarea Platformei</h2>
    <ol>
        <li>Toți utilizatorii trebuie să aibă cel puțin 16 ani pentru a crea cont.</li>
        <li>Conturile sunt personale și nu pot fi împărtășite. Siguranța contului vă aparține.</li>
        <li>Este interzisă folosirea site-ului pentru spam, publicitate neautorizată sau orice activitate ilegală.</li>
        <li>Accesul la anumite funcționalități (precum `Atelierul` de articole) este strict restricționat în funcție de rolul utilizatorului.</li>
    </ol>

    <hr>

    <h2>2. Publicarea de Conținut (pentru autori)</h2>
    <ol>
        <li>Orice articol publicat trebuie să fie original și să respecte în totalitate drepturile de autor. Plagiatul nu este tolerat.</li>
        <li>Este strict interzis conținutul care promovează ură, violență, discriminare, sau are caracter pornografic.</li>
        <li>Articolele trebuie să fie corecte din punct de vedere factual și să citeze sursele de referință atunci când este necesar.</li>
        <li>Administratorii își rezervă dreptul de a șterge sau modifica articolele care încalcă aceste reguli, fără notificare prealabilă.</li>
    </ol>
    
    <hr>

    <h2>3. Comentarii și Interacțiuni</h2>
    <ol>
        <li>Comentariile trebuie să fie constructive, relevante și, cel mai important, respectuoase față de autori și ceilalți cititori.</li>
        <li>Nu este permis limbajul jignitor, amenințările, atacurile personale (`ad hominem`) sau trolling-ul.</li>
        <li>Comentariile nu trebuie să conțină date personale (nume complete, adrese, telefoane) ale altor utilizatori.</li>
        <li>Administratorii pot elimina comentariile care încalcă aceste reguli și pot suspenda conturile repetitiv abuzive.</li>
    </ol>
    
    <hr>

    <h2>4. Cereri pentru Rolul de Autor</h2>
    <ol>
        <li>Utilizatorii înregistrați cu rolul de Cititor pot solicita să devină Autori prin formularul `Echipă`.</li>
        <li>Cererea trebuie să fie însoțită de o justificare clară și motivată care să demonstreze dedicarea și calitatea intențiilor.</li>
        <li>Administratorii analizează cererile în cel mai scurt timp și decid acceptarea sau respingerea lor.</li>
        <li>Decizia administratorilor este finală și nu poate fi contestată pe site.</li>
    </ol>
    
    <hr>

    <h2>5. Protecția Datelor și Confidențialitate</h2>
    <ol>
        <li>Toate datele personale sunt gestionate conform legislației în vigoare (GDPR, unde este cazul).</li>
        <li>Este interzisă folosirea informațiilor personale sau de contact ale altor utilizatori în scop comercial sau ilegal.</li>
        <li>Administratorii nu vor divulga datele personale fără consimțământul utilizatorilor, cu excepția cazurilor prevăzute expres de lege.</li>
    </ol>
    
    <hr>

    <h2>6. Sancțiuni și Responsabilități</h2>
    <ol>
        <li>Încălcarea regulilor, în funcție de gravitate, poate duce la avertisment, suspendarea temporară a contului sau banarea definitivă.</li>
        <li>Utilizatorii sunt exclusiv responsabili pentru conținutul pe care îl publică.</li>
        <li>Administratorii nu sunt responsabili pentru pierderi sau daune indirecte cauzate de utilizarea neconformă a site-ului.</li>
    </ol>

</div>

<footer class="footer">
    <p>&copy; 2025 PANORAMA Revistă. Toate drepturile rezervate.</p>
    <div class="social-links">
        <a href="#">Facebook</a> | <a href="#">Instagram</a> | <a href="#">Twitter</a>
    </div>
</footer>

</body>
</html>
