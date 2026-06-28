<?php
/**
 * Seed pagine "Associazione" del menù principale.
 * Eseguito tramite wp-cli:
 *
 *   docker compose --profile tools run --rm wpcli eval-file /scripts/seed-pages.php
 *
 * Idempotente: aggiorna i contenuti se la pagina esiste già (basato sullo slug).
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
    fwrite( STDERR, "Questo script va eseguito via wp-cli.\n" ); exit( 1 );
}

function gfoss_seed_page( string $slug, string $title, int $parent_id, string $content, ?int $menu_order = null ): int {
    // Cerca per slug + parent: get_page_by_path() vuole il path completo e fallirebbe
    // sulle pagine figlie (es. "associazione/statuto"), duplicandole a ogni run.
    $found = get_posts( [
        'post_type'   => 'page',
        'name'        => $slug,
        'post_parent' => $parent_id,
        'post_status' => 'any',
        'numberposts' => 1,
    ] );
    $existing = $found ? $found[0] : null;
    $args = [
        'post_type'    => 'page',
        'post_status'  => 'publish',
        'post_title'   => $title,
        'post_name'    => $slug,
        'post_content' => $content,
        'post_parent'  => $parent_id,
    ];
    if ( $menu_order !== null ) { $args['menu_order'] = $menu_order; }

    if ( $existing ) {
        $args['ID'] = $existing->ID;
        wp_update_post( $args );
        WP_CLI::log( "  ↻ aggiornata: $title (#$existing->ID)" );
        return $existing->ID;
    }
    $id = wp_insert_post( $args );
    WP_CLI::log( "  ✓ creata:    $title (#$id)" );
    return (int) $id;
}

/** Importa un file in Media Library e ritorna l'attachment id (0 se fallisce). */
function gfoss_seed_media( string $path, string $title ): int {
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';
    $tmp = wp_tempnam( $path );
    if ( ! $tmp || ! @copy( $path, $tmp ) ) { return 0; }
    $id = media_handle_sideload( [ 'name' => basename( $path ), 'tmp_name' => $tmp ], 0, $title );
    if ( is_wp_error( $id ) ) { @unlink( $tmp ); return 0; }
    return (int) $id;
}

/**
 * Importa un file dagli asset del tema nella Media Library e ne ritorna l'ID.
 * Idempotente: se già importato (marcato con _gfoss_seed_src) riusa l'attachment.
 */
function gfoss_seed_media_from_theme( string $rel_path, string $title ): int {
    $existing = get_posts( [
        'post_type'   => 'attachment',
        'meta_key'    => '_gfoss_seed_src',
        'meta_value'  => $rel_path,
        'numberposts' => 1,
        'post_status' => 'inherit',
    ] );
    if ( $existing ) { return (int) $existing[0]->ID; }

    $src = get_theme_file_path( $rel_path );
    if ( ! file_exists( $src ) ) { return 0; }

    $upload = wp_upload_dir();
    $dest   = trailingslashit( $upload['path'] ) . wp_unique_filename( $upload['path'], basename( $src ) );
    if ( ! copy( $src, $dest ) ) { return 0; }

    $type = wp_check_filetype( basename( $dest ) );
    $id   = wp_insert_attachment( [
        'post_mime_type' => $type['type'],
        'post_title'     => $title,
        'post_status'    => 'inherit',
    ], $dest );
    if ( ! $id ) { return 0; }

    require_once ABSPATH . 'wp-admin/includes/image.php';
    wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $dest ) );
    update_post_meta( $id, '_gfoss_seed_src', $rel_path );
    return (int) $id;
}

WP_CLI::log( '== Seed pagine GFOSS.it ==' );

// 1. Associazione (parent)
$assoc_id = gfoss_seed_page( 'associazione', 'Associazione', 0, <<<HTML
<!-- wp:heading --><h2>Chi siamo</h2><!-- /wp:heading -->
<!-- wp:paragraph --><p>GFOSS.it è stata fondata il <strong>16 febbraio 2007</strong> da specialisti con esperienza nel campo dei sistemi informativi territoriali e tecnologie open-source, durante l'ottavo meeting degli utenti italiani GRASS a Palermo.</p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p>L'associazione si prefigge di favorire lo sviluppo, la diffusione e la tutela del software libero e open source per l'informazione geografica, di promuovere standard aperti e libero accesso ai dati geografici, di coordinare traduzioni e localizzazione di programmi e di mantenere relazioni con altre associazioni nazionali e internazionali.</p><!-- /wp:paragraph -->
<!-- wp:heading {"level":3} --><h3>Riconoscimenti</h3><!-- /wp:heading -->
<!-- wp:list --><ul>
<li><strong>12 settembre 2007</strong> — riconosciuta come <strong>OSGeo Local Chapter italiano</strong> dalla Open Source Geospatial Foundation</li>
<li><strong>31 ottobre 2013</strong> — firma della convenzione con la Regione Toscana per la diffusione del software GIS open source</li>
</ul><!-- /wp:list -->
HTML
, 1 );

// 2. Statuto (testo integrale)
gfoss_seed_page( 'statuto', 'Statuto', $assoc_id, <<<HTML
<!-- wp:list --><ul>
<li><strong>Denominazione:</strong> GFOSS.it APS</li>
<li><strong>Sede legale:</strong> Lungargine Gerolamo Rovetta 28, 35131 Padova</li>
<li><strong>Codice fiscale:</strong> 95090860131</li>
<li><strong>Forma giuridica:</strong> Associazione di Promozione Sociale — Ente del Terzo Settore (D.Lgs. 117/2017), iscritta al RUNTS</li>
</ul><!-- /wp:list -->

<!-- wp:heading {"level":3} --><h3>Premessa</h3><!-- /wp:heading -->
<!-- wp:paragraph --><p>L'Associazione Italiana per l'Informazione Geografica Libera - GFOSS.it nasce nel 2007 grazie alle prime comunità del software libero italiano legate a GRASS, che decisero di riunirsi in associazione per portare avanti la loro principale missione legata allo sviluppo, la diffusione e la tutela del software esclusivamente libero e a "codice aperto" (open source), oltre che alla promozione degli standard aperti per l'informazione geografica, il libero accesso ai dati geografici e il trasferimento tecnologico, nel motto di "Free as in Freedom".</p><!-- /wp:paragraph -->

<!-- wp:heading {"level":3} --><h3>Art. 1 — Denominazione, sede e durata</h3><!-- /wp:heading -->
<!-- wp:paragraph --><p>È costituita un'associazione di promozione sociale nella forma di associazione riconosciuta, apartitica e aconfessionale, quale Ente del Terzo Settore, nel rispetto del D. Lgs. 117/2017 e s.m.i., del Codice civile e della normativa in materia, denominata "GFOSS.it APS".</p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p>L'Associazione ha sede legale in Lungargine Gerolamo Rovetta n. 28, cap. 35131, nel comune di Padova. Potranno essere istituite sedi secondarie e succursali. L'associazione ha durata indeterminata.</p><!-- /wp:paragraph -->

<!-- wp:heading {"level":3} --><h3>Art. 2 — Statuto</h3><!-- /wp:heading -->
<!-- wp:paragraph --><p>L'associazione di promozione sociale è disciplinata dal presente Statuto e agisce nel rispetto del D. Lgs. 117/2017 e s.m.i., delle relative norme di attuazione, della specifica legge regionale e dei principi generali dell'ordinamento giuridico.</p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p>L'assemblea degli associati può deliberare l'eventuale regolamento di esecuzione dello Statuto per la disciplina degli aspetti organizzativi più particolari.</p><!-- /wp:paragraph -->

<!-- wp:heading {"level":3} --><h3>Art. 3 — Efficacia dello statuto</h3><!-- /wp:heading -->
<!-- wp:paragraph --><p>Lo Statuto vincola alla sua osservanza gli associati all'associazione; esso costituisce la regola fondamentale di comportamento dell'attività dell'associazione stessa.</p><!-- /wp:paragraph -->

<!-- wp:heading {"level":3} --><h3>Art. 4 — Interpretazione dello statuto</h3><!-- /wp:heading -->
<!-- wp:paragraph --><p>Lo Statuto è valutato secondo le regole dei contratti e secondo i criteri dell'articolo 12 delle preleggi al codice civile.</p><!-- /wp:paragraph -->

<!-- wp:heading {"level":3} --><h3>Art. 5 — Finalità e Attività</h3><!-- /wp:heading -->
<!-- wp:paragraph --><p>L'associazione esercita in via esclusiva o principale una o più attività di interesse generale per il perseguimento, senza scopo di lucro, di finalità civiche, solidaristiche e di utilità sociale.</p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p>Lo scopo generale dell'associazione è lo sviluppo, la diffusione e la tutela del software geografico libero, oltre alla promozione degli standard aperti per l'informazione geografica, il libero accesso ai dati (geospaziali e non) e il trasferimento tecnologico, ponendosi come autorevole soggetto di riferimento nei confronti della Pubblica Amministrazione, delle imprese, dei professionisti, degli Enti di Formazione e della società civile tutta, rispetto ai temi statutari.</p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p>Le attività che si propone di svolgere, prevalentemente in favore dei propri associati, di loro familiari o di terzi, avvalendosi in modo prevalente dell'attività di volontariato dei propri associati, sono:</p><!-- /wp:paragraph -->
<!-- wp:list --><ul>
<li>attività culturali di interesse sociale con finalità educativa, ai sensi dell'articolo 5, comma 1, del D.Lgs. 117/2017, lettera d);</li>
<li>organizzazione e gestione di attività culturali, artistiche o ricreative di interesse sociale, incluse attività, anche editoriali, di promozione e diffusione della cultura e della pratica del volontariato e delle attività di interesse generale di cui al presente articolo ai sensi dell'articolo 5, comma 1, del D.Lgs. 117/2017, lettera i).</li>
</ul><!-- /wp:list -->
<!-- wp:paragraph --><p>A titolo esemplificativo, ma non esaustivo, le azioni si concretizzano in:</p><!-- /wp:paragraph -->
<!-- wp:list --><ul>
<li>organizzazione di convegni e workshop;</li>
<li>supporto tecnico scientifico a Università ed Enti di ricerca;</li>
<li>organizzazione e promozione di eventi legati al software geografico libero nelle scuole e nelle università;</li>
<li>promozione delle comunità e degli eventi legati all'open source geografico in Italia;</li>
<li>azioni volte a favorire lo sviluppo, la diffusione e la tutela del software esclusivamente libero e open source per l'informazione geografica;</li>
<li>promozione di standard aperti per l'informazione geografica, il libero accesso ai dati (geografici e non) e il trasferimento tecnologico;</li>
<li>promozione dei contatti all'interno della comunità di utenti e sviluppatori del software libero e open source per l'informazione geografica, e fra la comunità e gli enti esterni;</li>
<li>azioni per favorire e coordinare la traduzione, la localizzazione e l'internazionalizzazione di programmi e manuali per l'informazione geografica, adattandoli alla realtà nazionale e internazionale;</li>
<li>avvio di relazioni con altre Associazioni Nazionali e Internazionali e con Enti Pubblici e Privati finalizzate alla realizzazione di iniziative in armonia con gli scopi previsti dal presente statuto;</li>
<li>sostegno alle comunità locali dei software geografici liberi attraverso la creazione di gruppi di lavoro interni all'associazione e progetti di incubazione, promozione e raccolta fondi specifici per il determinato progetto/comunità di utenti.</li>
</ul><!-- /wp:list -->
<!-- wp:paragraph --><p>L'associazione può esercitare, a norma dell'art. 6 del Codice del terzo settore, attività diverse da quelle di interesse generale, secondarie e strumentali rispetto a queste ultime, secondo criteri e limiti definiti con apposito Decreto ministeriale. La loro individuazione è operata da parte del Consiglio Direttivo.</p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p>L'associazione può inoltre realizzare attività di raccolta fondi, nel rispetto dei principi di verità, trasparenza e correttezza con i sostenitori e con il pubblico, in conformità alle disposizioni contenute nell'art. 7 del D. Lgs. 117/2017.</p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p>GFOSS.it APS può collaborare e federarsi con associazioni e istituzioni affini, analoghe o complementari, operanti in Italia od altrove, purché gli accordi non siano in contrasto con l'attuazione degli scopi sociali e non ne limitino la libertà.</p><!-- /wp:paragraph -->

<!-- wp:heading {"level":3} --><h3>Art. 6 — Ammissione</h3><!-- /wp:heading -->
<!-- wp:paragraph --><p>Sono associati dell'associazione le persone fisiche che condividono le finalità e gli scopi associativi e si impegnano per realizzare le attività di interesse generale.</p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p>Il numero degli associati è illimitato ma, in ogni caso, non può essere inferiore al numero minimo richiesto dalla Legge. Se successivamente alla costituzione il numero dovesse scendere al di sotto del minimo richiesto, l'associazione dovrà darne tempestiva comunicazione all'Ufficio del Registro unico nazionale e integrare il numero entro un anno.</p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p>L'ammissione all'associazione è deliberata dal Consiglio Direttivo su domanda dell'interessato secondo criteri non discriminatori, coerenti con le finalità perseguite e le attività d'interesse generale. La deliberazione è comunicata all'interessato e annotata nel libro degli associati.</p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p>In caso di rigetto della domanda, il Consiglio Direttivo comunica la decisione all'interessato entro 60 (sessanta) giorni, motivandola. L'aspirante associato può, entro 60 (sessanta) giorni da tale comunicazione di rigetto, chiedere che sull'istanza si pronunci l'assemblea in occasione della successiva convocazione.</p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p>L'ammissione ad associato è a tempo indeterminato, fermo restando il diritto di recesso. Non è ammessa la categoria di associati temporanei. La quota sociale è intrasmissibile, non rimborsabile e non rivalutabile.</p><!-- /wp:paragraph -->

<!-- wp:heading {"level":3} --><h3>Art. 7 — Diritti e doveri degli associati</h3><!-- /wp:heading -->
<!-- wp:paragraph --><p>Gli associati hanno pari diritti e doveri. Hanno il diritto di:</p><!-- /wp:paragraph -->
<!-- wp:list --><ul>
<li>eleggere gli organi sociali e di essere eletti negli stessi;</li>
<li>essere informati sulle attività dell'associazione e controllarne l'andamento;</li>
<li>prendere atto dell'ordine del giorno delle assemblee;</li>
<li>esaminare i libri sociali secondo le regole stabilite dal successivo art. 18;</li>
<li>votare in Assemblea se iscritti da almeno 1 (un) mese nel libro degli associati e in regola con il pagamento della quota associativa;</li>
<li>denunziare i fatti che ritiene censurabili ai sensi dell'art. 29 del Codice del terzo settore;</li>
</ul><!-- /wp:list -->
<!-- wp:paragraph --><p>e il dovere di:</p><!-- /wp:paragraph -->
<!-- wp:list --><ul>
<li>rispettare il presente statuto e l'eventuale regolamento interno;</li>
<li>versare la quota associativa secondo l'importo, le modalità di versamento e i termini annualmente stabiliti dall'assemblea.</li>
</ul><!-- /wp:list -->

<!-- wp:heading {"level":3} --><h3>Art. 8 — Volontario e attività di volontariato</h3><!-- /wp:heading -->
<!-- wp:paragraph --><p>L'associato volontario svolge la propria attività in favore della comunità e del bene comune in modo personale, spontaneo e gratuito, senza fini di lucro, neanche indiretti ed esclusivamente per fini di solidarietà.</p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p>La qualità di associato volontario è incompatibile con qualsiasi forma di rapporto di lavoro subordinato o autonomo e con ogni altro rapporto di lavoro retribuito con l'associazione.</p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p>L'attività dell'associato volontario non può essere retribuita in alcun modo, nemmeno dal beneficiario. Agli associati volontari possono essere rimborsate soltanto le spese effettivamente sostenute e documentate per l'attività prestata, entro i limiti massimi e alle condizioni preventivamente stabilite dall'associazione. Sono vietati i rimborsi spesa di tipo forfetario.</p><!-- /wp:paragraph -->

<!-- wp:heading {"level":3} --><h3>Art. 9 — Perdita della qualità di associato</h3><!-- /wp:heading -->
<!-- wp:paragraph --><p>La qualità di associato si perde per morte, recesso o esclusione, o per mancato pagamento della quota associativa entro i termini.</p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p>L'associato può recedere dall'associazione mediante comunicazione scritta al Consiglio Direttivo.</p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p>L'associato che contravviene per gravi motivi ai doveri stabiliti dallo statuto, può essere escluso dall'associazione. L'esclusione è deliberata dal Consiglio Direttivo con voto segreto e dopo aver ascoltato le giustificazioni dell'interessato. La deliberazione di esclusione dovrà essere comunicata adeguatamente all'associato e all'assemblea degli associati.</p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p>L'associato può ricorrere all'autorità giudiziaria entro sei mesi dal giorno di notifica della deliberazione.</p><!-- /wp:paragraph -->

<!-- wp:heading {"level":3} --><h3>Art. 10 — Gli organi dell'associazione</h3><!-- /wp:heading -->
<!-- wp:paragraph --><p>Sono organi dell'associazione:</p><!-- /wp:paragraph -->
<!-- wp:list --><ul>
<li>Assemblea degli associati;</li>
<li>Consiglio Direttivo;</li>
<li>Presidente;</li>
<li>Organo di controllo.</li>
</ul><!-- /wp:list -->
<!-- wp:paragraph --><p>Nei casi previsti dalla legge, deve essere nominato anche un Revisore Legale dei Conti.</p><!-- /wp:paragraph -->

<!-- wp:heading {"level":3} --><h3>Art. 11 — L'assemblea</h3><!-- /wp:heading -->
<!-- wp:paragraph --><p>L'assemblea è composta dagli associati dell'associazione, iscritti nel Libro degli associati e in regola con il versamento della quota sociale. È l'organo sovrano.</p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p>Ciascun associato ha diritto ad un voto e può farsi rappresentare da altro associato, conferendo delega scritta, anche in calce all'avviso di convocazione. Ciascun associato può rappresentare sino ad un massimo di tre associati. Qualora l'assemblea debba eleggere i nuovi membri degli organi sociali, gli associati candidati a ricoprire queste cariche associative non possono avere deleghe per le votazioni che li riguardano direttamente e indirettamente.</p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p>L'assemblea è presieduta dal Presidente dell'associazione o, in sua assenza, dal Vicepresidente o persona nominata a presidente dai convenuti all'assemblea stessa.</p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p>È convocata almeno una volta all'anno dal Presidente dell'associazione o da chi ne fa le veci mediante avviso scritto da inviare almeno 15 (quindici) giorni prima di quello fissato per l'adunanza e contenente la data della riunione, l'orario, il luogo, l'ordine del giorno e l'eventuale data di seconda convocazione. Tale comunicazione può avvenire a mezzo lettera, fax, e-mail spedita/divulgata al recapito risultante dal libro degli associati e/o mediante avviso affisso nella sede dell'associazione e/o mediante comunicazione sul sito web o altre modalità telematiche che garantiscano la prova dell'avvenuta ricezione.</p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p>L'assemblea è inoltre convocata a richiesta di almeno un decimo degli associati o quando il Consiglio Direttivo lo ritiene necessario.</p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p>L'Assemblea può essere svolta anche in audio-video conferenza, purché ricorrano le seguenti condizioni: sia consentito al Presidente dell'assemblea accertare l'identità degli intervenuti; sia consentito al verbalizzante di percepire i fatti e gli atti compiuti nella riunione assembleare; sia consentito a tutti gli intervenuti di partecipare alla discussione, alla votazione simultanea, agli argomenti posti all'ordine del giorno e visionare e trasmettere documenti.</p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p>I voti sono palesi. Delle riunioni dell'assemblea è redatto il verbale, sottoscritto dal Presidente e dal verbalizzante e conservato presso la sede dell'associazione. L'assemblea può essere ordinaria o straordinaria. È straordinaria quella convocata per la modifica dello statuto e lo scioglimento dell'associazione. È ordinaria in tutti gli altri casi.</p><!-- /wp:paragraph -->

<!-- wp:heading {"level":3} --><h3>Art. 12 — Compiti dell'assemblea</h3><!-- /wp:heading -->
<!-- wp:paragraph --><p>L'assemblea:</p><!-- /wp:paragraph -->
<!-- wp:list --><ul>
<li>determina le linee generali programmatiche dell'attività dell'associazione;</li>
<li>approva il bilancio di esercizio e il bilancio dell'associazione, quando previsto;</li>
<li>nomina e revoca i componenti degli organi dell'associazione;</li>
<li>delibera sulla responsabilità dei componenti degli organi e promuove azione di responsabilità nei loro confronti;</li>
<li>delibera le modificazioni dell'atto costitutivo o dello statuto;</li>
<li>approva i regolamenti;</li>
<li>delibera sullo scioglimento, sulla trasformazione, sulla fusione o sulla scissione dell'associazione;</li>
<li>delibera sugli altri oggetti attribuiti dalla legge, dall'atto costitutivo o dallo statuto alla sua competenza.</li>
</ul><!-- /wp:list -->

<!-- wp:heading {"level":3} --><h3>Art. 13 — Assemblea ordinaria</h3><!-- /wp:heading -->
<!-- wp:paragraph --><p>L'assemblea ordinaria è regolarmente costituita in prima convocazione con la presenza della metà più uno degli associati, presenti in proprio o per delega, e in seconda convocazione qualunque sia il numero degli associati presenti, in proprio o in delega. L'assemblea delibera a maggioranza dei voti dei presenti.</p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p>È ammessa l'espressione del voto per corrispondenza o in via elettronica, purché sia possibile verificare l'identità dell'associato che partecipa e vota. Nelle deliberazioni di approvazione del bilancio e in quelle che riguardano la loro responsabilità, gli amministratori non hanno diritto di voto.</p><!-- /wp:paragraph -->

<!-- wp:heading {"level":3} --><h3>Art. 14 — Assemblea straordinaria</h3><!-- /wp:heading -->
<!-- wp:paragraph --><p>L'assemblea straordinaria modifica lo statuto dell'associazione con la presenza di almeno metà più uno e il voto favorevole della maggioranza dei presenti e delibera lo scioglimento e la liquidazione nonché la devoluzione del patrimonio con il voto favorevole di almeno 3/4 (tre quarti) degli associati.</p><!-- /wp:paragraph -->

<!-- wp:heading {"level":3} --><h3>Art. 15 — Consiglio Direttivo</h3><!-- /wp:heading -->
<!-- wp:paragraph --><p>Il Consiglio Direttivo governa l'associazione e opera in attuazione delle volontà e degli indirizzi generali dell'assemblea alla quale risponde direttamente e dalla quale può essere revocato.</p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p>Il Consiglio Direttivo è composto da 3 (tre) a 5 (cinque) membri eletti dall'assemblea tra gli associati. Dura in carica per n. 3 (tre) anni e i suoi componenti possono essere rieletti per n. 3 (tre) mandati consecutivi.</p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p>Il Consiglio Direttivo è validamente costituito quando è presente la maggioranza dei componenti. Nel caso in cui è composto da soli tre membri esso è validamente costituito quando sono presenti tutti. Le deliberazioni sono assunte a maggioranza dei presenti. Si applica l'articolo 2382 del codice civile. Al conflitto di interessi degli amministratori si applica l'articolo 2475-ter del codice civile.</p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p>Il Consiglio Direttivo compie tutti gli atti di ordinaria e straordinaria amministrazione la cui competenza non sia per Legge di pertinenza esclusiva dell'assemblea. In particolare, tra gli altri compiti:</p><!-- /wp:paragraph -->
<!-- wp:list --><ul>
<li>amministra l'associazione;</li>
<li>nomina il presidente, il vicepresidente, il tesoriere dell'associazione;</li>
<li>convoca l'assemblea;</li>
<li>determina l'ammontare della quota associativa;</li>
<li>attua le deliberazioni dell'assemblea;</li>
<li>predispone il bilancio di esercizio, e, se previsto, il bilancio dell'associazione, li sottopone all'approvazione dell'assemblea e cura gli ulteriori adempimenti previsti dalla legge;</li>
<li>vigila sui decrementi che il patrimonio dell'associazione subisca e adotta senza indugio ogni occorrente provvedimento previsto dalla normativa applicabile;</li>
<li>predispone tutti gli elementi utili all'assemblea per la previsione e la programmazione economica dell'esercizio;</li>
<li>stipula tutti gli atti e contratti inerenti le attività associative;</li>
<li>cura la tenuta dei libri sociali di sua competenza;</li>
<li>è responsabile degli adempimenti connessi all'iscrizione nel Registro Unico Nazionale del Terzo Settore (RUNTS);</li>
<li>disciplina l'ammissione e l'esclusione degli associati;</li>
<li>accoglie o rigetta le domande degli aspiranti associati.</li>
</ul><!-- /wp:list -->
<!-- wp:paragraph --><p>Il potere di rappresentanza attribuito ai consiglieri è generale, pertanto le limitazioni di tale potere non sono opponibili ai terzi se non iscritte nel RUNTS o se non si prova che i terzi ne erano a conoscenza. Il presidente dell'associazione è il presidente del Consiglio Direttivo ed è nominato all'interno del Consiglio Direttivo.</p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p>Il Consiglio Direttivo si riunisce previa convocazione da effettuarsi mediante avviso contenente l'indicazione del giorno, dell'ora, del luogo dell'adunanza e l'elenco delle materie da trattare, spedito a mezzo di strumento di comunicazione che garantisca la prova dell'avvenuta ricezione. La convocazione deve pervenire a ciascuno degli aventi diritto almeno 7 (sette) giorni prima della riunione, salvi i casi di urgenza indifferibile in cui può essere convocato con preavviso di almeno 48 (quarantotto) ore.</p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p>Il Consiglio Direttivo è validamente costituito anche in assenza di formale convocazione, quando siano presenti tutti i componenti in carica e i componenti dell'organo di controllo siano stati informati e non vi si oppongano. Il Consiglio Direttivo delibera a maggioranza e in caso di parità prevale il voto del presidente. Le riunioni possono essere svolte anche in audio-video conferenza, alle medesime condizioni previste per l'assemblea. Le deliberazioni dovranno essere trascritte sul libro verbali del Consiglio.</p><!-- /wp:paragraph -->

<!-- wp:heading {"level":3} --><h3>Art. 16 — Il Presidente</h3><!-- /wp:heading -->
<!-- wp:paragraph --><p>Il presidente è nominato all'interno del Consiglio Direttivo a maggioranza dei componenti, rappresenta legalmente l'associazione e compie tutti gli atti che la impegnano verso l'esterno.</p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p>Il presidente dura in carica quanto il Consiglio Direttivo e cessa per scadenza del mandato, per dimissioni volontarie o per eventuale revoca decisa dall'assemblea. Almeno un mese prima della scadenza del mandato, il presidente convoca l'assemblea per l'elezione del nuovo presidente e del Consiglio Direttivo.</p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p>Il presidente convoca e presiede l'assemblea e il Consiglio Direttivo, svolge l'ordinaria amministrazione sulla base delle direttive di tali organi, riferendo al Consiglio Direttivo in merito all'attività compiuta. Il Vicepresidente sostituisce il Presidente in ogni sua attribuzione ogniqualvolta questi sia impossibilitato nell'esercizio delle sue funzioni.</p><!-- /wp:paragraph -->

<!-- wp:heading {"level":3} --><h3>Art. 17 — Organo di controllo</h3><!-- /wp:heading -->
<!-- wp:paragraph --><p>L'organo di controllo, anche monocratico, è nominato nei casi e nei modi previsti dall'art. 30 del D. Lgs. 117/2017. L'organo di controllo:</p><!-- /wp:paragraph -->
<!-- wp:list --><ul>
<li>vigila sull'osservanza della legge, dello statuto e sul rispetto dei principi di corretta amministrazione;</li>
<li>vigila sull'adeguatezza dell'assetto organizzativo, amministrativo e contabile e sul suo concreto funzionamento;</li>
<li>esercita compiti di monitoraggio dell'osservanza delle finalità civiche, solidaristiche e di utilità sociale;</li>
<li>attesta che il bilancio sociale sia stato redatto in conformità alle linee guida di cui all'articolo 14. Il bilancio sociale dà atto degli esiti del monitoraggio svolto.</li>
</ul><!-- /wp:list -->
<!-- wp:paragraph --><p>Può esercitare, al superamento dei limiti stabiliti dal D.Lgs. 117/17 all'art. 31, la revisione legale dei conti. In tal caso è costituito da revisori legali iscritti nell'apposito registro. Il componente dell'organo di controllo può in qualsiasi momento procedere ad atti di ispezione e di controllo e, a tal fine, può chiedere agli amministratori notizie sull'andamento delle operazioni dell'associazione o su determinati affari.</p><!-- /wp:paragraph -->

<!-- wp:heading {"level":3} --><h3>Art. 18 — Libri sociali</h3><!-- /wp:heading -->
<!-- wp:paragraph --><p>L'associazione ha l'obbligo di tenere i seguenti libri sociali:</p><!-- /wp:paragraph -->
<!-- wp:list --><ul>
<li>a) il libro degli associati tenuto a cura del Consiglio Direttivo;</li>
<li>b) il libro delle adunanze e delle deliberazioni delle assemblee, in cui devono essere trascritti anche i verbali redatti per atto pubblico, tenuto a cura del consiglio;</li>
<li>c) il libro delle adunanze e delle deliberazioni del Consiglio Direttivo, dell'organo di controllo, e degli altri organi sociali, tenuti a cura dell'organo a cui si riferiscono;</li>
<li>d) il registro dei volontari, tenuto a cura del Consiglio Direttivo.</li>
</ul><!-- /wp:list -->
<!-- wp:paragraph --><p>Tutti gli associati, in regola con il versamento della quota associativa, hanno il diritto di esaminare i libri tenuti presso la sede legale dell'ente, entro 30 (trenta) giorni dalla data della richiesta formulata al consiglio direttivo.</p><!-- /wp:paragraph -->

<!-- wp:heading {"level":3} --><h3>Art. 19 — Risorse economiche</h3><!-- /wp:heading -->
<!-- wp:paragraph --><p>Il patrimonio dell'associazione è composto dalla dotazione iniziale di Euro 15.000 (quindicimila). Le risorse economiche dell'associazione sono costituite da:</p><!-- /wp:paragraph -->
<!-- wp:list --><ul>
<li>quote associative;</li>
<li>contributi pubblici e privati;</li>
<li>donazioni e lasciti testamentari;</li>
<li>rendite patrimoniali;</li>
<li>attività di raccolta fondi;</li>
<li>rimborsi da convenzioni;</li>
<li>proventi da cessioni di beni e servizi agli associati e a terzi, anche attraverso lo svolgimento di attività economiche di natura commerciale, artigianale o agricola, svolte in maniera ausiliaria e sussidiaria e comunque finalizzate al raggiungimento degli obiettivi istituzionali;</li>
<li>ogni altra entrata ammessa ai sensi del D.Lgs. 117/2017.</li>
</ul><!-- /wp:list -->
<!-- wp:paragraph --><p>Qualora il patrimonio risultasse diminuito di oltre 1/3 (un terzo) dell'importo minimo stabilito dalla legge, l'organo amministrativo senza indugio dovrà provvedere alla ricostruzione del patrimonio minimo ovvero convocare l'assemblea per deliberare la trasformazione, la prosecuzione dell'attività in forma di associazione non riconosciuta, la fusione o lo scioglimento dell'Ente.</p><!-- /wp:paragraph -->

<!-- wp:heading {"level":3} --><h3>Art. 20 — I beni</h3><!-- /wp:heading -->
<!-- wp:paragraph --><p>I beni dell'associazione sono beni immobili, beni mobili registrati e beni mobili. I beni immobili e i beni mobili registrati possono essere acquistati dall'associazione, e sono ad essa intestati. I beni immobili, i beni mobili registrati, nonché i beni mobili che sono collocati nella sede dell'associazione sono elencati nell'inventario, che è depositato presso la sede dell'associazione e può essere consultato dagli associati.</p><!-- /wp:paragraph -->

<!-- wp:heading {"level":3} --><h3>Art. 21 — Divieto di distribuzione degli utili e obbligo di utilizzo del patrimonio</h3><!-- /wp:heading -->
<!-- wp:paragraph --><p>L'associazione ha il divieto di distribuire, anche in modo indiretto, utili e avanzi di gestione nonché fondi, riserve o capitale durante la propria vita ai sensi dell'art. 8, comma 2, del D.Lgs. 117/2017 nonché l'obbligo di utilizzare il patrimonio, comprensivo di eventuali ricavi, rendite, proventi, entrate comunque denominate, per lo svolgimento dell'attività statutaria ai fini dell'esclusivo perseguimento delle finalità previste.</p><!-- /wp:paragraph -->

<!-- wp:heading {"level":3} --><h3>Art. 22 — Bilancio</h3><!-- /wp:heading -->
<!-- wp:paragraph --><p>Il bilancio di esercizio dell'associazione è annuale e decorre dal primo gennaio di ogni anno e si chiude il 31 (trentuno) dicembre di ogni anno. È redatto ai sensi degli articoli 13 e 87 del D. Lgs. 117/2017 e delle relative norme di attuazione e deve rappresentare in maniera veritiera e corretta l'andamento economico e finanziario dell'associazione.</p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p>Il bilancio è predisposto dal consiglio direttivo e viene approvato dall'assemblea entro 5 (cinque) mesi dalla chiusura dell'esercizio cui si riferisce il consuntivo e depositato presso il Registro unico nazionale del terzo settore entro il 30 (trenta) giugno di ogni anno.</p><!-- /wp:paragraph -->

<!-- wp:heading {"level":3} --><h3>Art. 23 — Bilancio sociale</h3><!-- /wp:heading -->
<!-- wp:paragraph --><p>È redatto nei casi e modi previsti dall'art. 14 del D. Lgs. 117/2017.</p><!-- /wp:paragraph -->

<!-- wp:heading {"level":3} --><h3>Art. 24 — Convenzioni</h3><!-- /wp:heading -->
<!-- wp:paragraph --><p>Le convenzioni tra l'associazione di promozione sociale e le Amministrazioni pubbliche di cui all'art. 56, comma 1, del D. Lgs. 117/2017, sono deliberate dal Consiglio Direttivo che ne determina anche le modalità di attuazione, e sono stipulate dal Presidente dell'associazione, quale suo legale rappresentante. Copia di ogni convenzione è custodita, a cura del presidente, presso la sede dell'associazione.</p><!-- /wp:paragraph -->

<!-- wp:heading {"level":3} --><h3>Art. 25 — Personale retribuito</h3><!-- /wp:heading -->
<!-- wp:paragraph --><p>L'associazione di promozione sociale può avvalersi di personale retribuito nei limiti previsti dall'art. 36 del D. Lgs. 117/2017. I rapporti tra l'associazione e il personale retribuito sono disciplinati dalla legge e da apposito regolamento adottato dall'associazione.</p><!-- /wp:paragraph -->

<!-- wp:heading {"level":3} --><h3>Art. 26 — Responsabilità e assicurazione degli associati volontari</h3><!-- /wp:heading -->
<!-- wp:paragraph --><p>Gli associati volontari che prestano attività di volontariato sono assicurati per malattie, infortunio, e per la responsabilità civile verso i terzi ai sensi dell'art. 18 del D. Lgs. 117/2017.</p><!-- /wp:paragraph -->

<!-- wp:heading {"level":3} --><h3>Art. 27 — Responsabilità dell'associazione</h3><!-- /wp:heading -->
<!-- wp:paragraph --><p>Per le obbligazioni assunte dalle persone che rappresentano l'associazione, i terzi possono far valere i loro diritti sul fondo comune. Delle obbligazioni assunte rispondono, personalmente e solidalmente le persone che hanno agito in nome e per conto dell'associazione.</p><!-- /wp:paragraph -->

<!-- wp:heading {"level":3} --><h3>Art. 28 — Assicurazione dell'associazione</h3><!-- /wp:heading -->
<!-- wp:paragraph --><p>L'associazione di promozione sociale può assicurarsi per i danni derivanti da responsabilità contrattuale ed extra contrattuale dell'associazione stessa.</p><!-- /wp:paragraph -->

<!-- wp:heading {"level":3} --><h3>Art. 29 — Devoluzione del patrimonio</h3><!-- /wp:heading -->
<!-- wp:paragraph --><p>In caso di estinzione o scioglimento, il patrimonio residuo è devoluto, previo parere positivo del Registro competente, salva diversa destinazione imposta dalla legge, ad altri enti del Terzo settore, secondo quanto previsto dall'art. 9 del D. Lgs. 117/2017.</p><!-- /wp:paragraph -->

<!-- wp:heading {"level":3} --><h3>Art. 30 — Disposizioni finali</h3><!-- /wp:heading -->
<!-- wp:paragraph --><p>Per quanto non è previsto dal presente statuto, si fa riferimento alle normative vigenti in materia e ai principi generali dell'ordinamento giuridico.</p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p>L'acronimo ETS potrà essere inserito nella denominazione, in via automatica e sarà spendibile nei rapporti con i terzi, negli atti, nella corrispondenza e nelle comunicazioni con il pubblico solo dopo aver ottenuto l'iscrizione al Registro unico nazionale del terzo settore.</p><!-- /wp:paragraph -->
HTML
, 1 );

// 3. Bilanci e verbali (alimentati dal CPT "Bilanci e verbali" → shortcode [gfoss_bilanci])
gfoss_seed_page( 'bilanci-associazione', 'Bilanci e verbali', $assoc_id, <<<HTML
<!-- wp:paragraph --><p>In conformità al Codice del Terzo Settore (D.Lgs. 117/2017), GFOSS.it APS pubblica i propri bilanci di esercizio e i verbali delle assemblee dei soci. I bilanci sono approvati dall'Assemblea e depositati al RUNTS.</p><!-- /wp:paragraph -->
<!-- wp:shortcode -->
[gfoss_bilanci]
<!-- /wp:shortcode -->
<!-- wp:paragraph --><p class="gf-muted"><em>I documenti si gestiscono dall'area amministrativa, in «Associazione → Bilanci e verbali»: per ogni voce si sceglie il tipo (consuntivo, preventivo, verbale), l'anno e si allega il PDF.</em></p><!-- /wp:paragraph -->
HTML
, 2 );

// 5. Iscrizioni e Rinnovi (pagina UNICA: info + modulo di iscrizione)
$iscr_id = gfoss_seed_page( 'iscrizioni-rinnovi', 'Iscrizioni e Rinnovi', $assoc_id, <<<HTML
<!-- wp:heading --><h2>Nuove iscrizioni</h2><!-- /wp:heading -->
<!-- wp:paragraph --><p>Per iscriversi a GFOSS.it APS è possibile compilare il modulo online che trovi in fondo a questa pagina. Le iscrizioni sono soggette ad approvazione del Consiglio Direttivo (art. 6 dello Statuto).</p><!-- /wp:paragraph -->

<!-- wp:heading --><h2>Rinnovi</h2><!-- /wp:heading -->
<!-- wp:paragraph --><p>La quota associativa annuale è fissata in <strong>30,00 €</strong> e il rinnovo va effettuato entro il <strong>31 dicembre</strong> di ogni anno.</p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p>In base all'art. 11 dello Statuto, all'assemblea hanno diritto di intervenire e votare gli associati in regola con il pagamento. Pertanto per esercitare il diritto di voto è necessario essere in regola con il pagamento per l'anno in corso.</p><!-- /wp:paragraph -->

<!-- wp:heading {"level":3} --><h3>Modalità di pagamento</h3><!-- /wp:heading -->
<!-- wp:heading {"level":4} --><h4>Bonifico bancario</h4><!-- /wp:heading -->
<!-- wp:list --><ul>
<li>Banca: <strong>Intesa Sanpaolo S.p.A.</strong></li>
<li>IBAN: <code>IT85F0306909606100000015079</code></li>
<li>BIC: <code>BCITITMM</code> (solo per transazioni internazionali)</li>
<li>Beneficiario: <strong>Associazione Italiana per l'Informazione Geografica Libera</strong></li>
<li>Causale: <em>Nuova iscrizione anno XXXX</em> oppure <em>Rinnovo iscrizione anno XXXX</em></li>
</ul><!-- /wp:list -->
<!-- wp:paragraph --><p><em>Nota bene: il beneficiario dev'essere sempre indicato per esteso. In nessun caso scrivere semplicemente "GFOSS.it". Se i caratteri sono limitati, è preferibile scrivere il nome fino all'esaurimento dei caratteri disponibili piuttosto che usare abbreviazioni.</em></p><!-- /wp:paragraph -->

<!-- wp:heading {"level":4} --><h4>PayPal</h4><!-- /wp:heading -->
<!-- wp:paragraph --><p>I soci già iscritti possono rinnovare con un click dalla loro <a href="/area-soci/">area personale</a>. Per chi non è ancora socio, il pulsante PayPal compare dopo aver inviato il modulo di iscrizione qui sotto.</p><!-- /wp:paragraph -->

<!-- wp:heading --><h2>Domanda di iscrizione online</h2><!-- /wp:heading -->
<!-- wp:shortcode -->
[gfoss_iscrizione_form]
<!-- /wp:shortcode -->
HTML
, 4 );

// 5b. Unifica iscrizioni: il modulo vive ORA nella pagina "Iscrizioni e Rinnovi".
// Si ripunta l'option del plugin a questa pagina e si elimina la vecchia pagina
// "Iscriviti a GFOSS.it" duplicata (l'attivatore non la ricrea: l'option è valida).
$old_iscriviti = (int) get_option( 'gfoss_page_iscriviti' );
if ( $old_iscriviti && $old_iscriviti !== $iscr_id ) {
    wp_delete_post( $old_iscriviti, true );
    WP_CLI::log( "  ✗ rimossa pagina duplicata 'Iscriviti a GFOSS.it' (#$old_iscriviti)" );
}
update_option( 'gfoss_page_iscriviti', $iscr_id );
WP_CLI::log( "  ↻ modulo iscrizione unificato nella pagina 'Iscrizioni e Rinnovi' (#$iscr_id)" );

// 6. Organi associativi (Consiglio Direttivo in carica)
gfoss_seed_page( 'organi-associativi', 'Organi associativi', $assoc_id, <<<HTML
<!-- wp:paragraph --><p>Gli organi dell'associazione sono l'Assemblea dei soci e il Consiglio Direttivo (artt. 11–16 dello Statuto). Il Consiglio Direttivo è composto da 3 a 5 membri eletti dall'Assemblea, con mandato triennale rinnovabile per un massimo di tre mandati consecutivi.</p><!-- /wp:paragraph -->
<!-- wp:heading {"level":3} --><h3>Consiglio Direttivo in carica</h3><!-- /wp:heading -->
<!-- wp:list --><ul>
<li><strong>Presidente:</strong> Stefano Campus</li>
<li><strong>Vice-presidente:</strong> Federico Gianoli</li>
<li><strong>Segretario:</strong> Cristian Orlando</li>
<li><strong>Tesoriere:</strong> Alessandro Fanna</li>
<li><strong>Consigliere:</strong> Luca Delucchi</li>
<li><strong>Consigliere:</strong> Enrico Ferreguti</li>
</ul><!-- /wp:list -->
<!-- wp:paragraph --><p>Il Presidente ha la legale rappresentanza dell'associazione (art. 16 dello Statuto). Il Consiglio è responsabile degli adempimenti connessi all'iscrizione nel Registro Unico Nazionale del Terzo Settore (RUNTS).</p><!-- /wp:paragraph -->
HTML
, 5 );

// 7. Privacy policy (root, linkata nel footer)
gfoss_seed_page( 'privacy', 'Privacy Policy', 0, <<<HTML
<!-- wp:paragraph --><p>Informativa sul trattamento dei dati personali ai sensi del Regolamento UE 2016/679 (GDPR) e del D.Lgs. 196/2003 come modificato dal D.Lgs. 101/2018.</p><!-- /wp:paragraph -->
<!-- wp:heading {"level":3} --><h3>Titolare del trattamento</h3><!-- /wp:heading -->
<!-- wp:list --><ul>
<li><strong>GFOSS.it APS</strong> — Associazione Italiana per l'Informazione Geografica Libera</li>
<li>Sede legale: Lungargine Gerolamo Rovetta 28, 35131 Padova — C.F. 95090860131</li>
<li>Contatto: <a href="mailto:info@gfoss.it">info@gfoss.it</a></li>
</ul><!-- /wp:list -->
<!-- wp:heading {"level":3} --><h3>Dati trattati e finalità</h3><!-- /wp:heading -->
<!-- wp:list --><ul>
<li><strong>Dati di navigazione</strong> (log, indirizzo IP, browser): per la sicurezza e il corretto funzionamento del sito. Base giuridica: legittimo interesse.</li>
<li><strong>Dati di iscrizione socio</strong> (anagrafica, codice fiscale, recapiti): per la gestione del rapporto associativo, dei libri sociali e degli obblighi del Terzo Settore. Base giuridica: esecuzione del rapporto associativo e obblighi di legge.</li>
<li><strong>Email e newsletter</strong>: per comunicazioni associative, previo consenso ove richiesto.</li>
<li><strong>Pagamenti</strong> (quote, donazioni): gestiti tramite bonifico o PayPal; i dati di pagamento sono trattati dai rispettivi istituti come titolari autonomi.</li>
</ul><!-- /wp:list -->
<!-- wp:heading {"level":3} --><h3>Conservazione</h3><!-- /wp:heading -->
<!-- wp:paragraph --><p>I dati sono conservati per il tempo necessario alle finalità indicate e agli obblighi di legge (es. fiscali e del Terzo Settore). I dati di navigazione sono conservati per un massimo di 12 mesi.</p><!-- /wp:paragraph -->
<!-- wp:heading {"level":3} --><h3>Diritti dell'interessato</h3><!-- /wp:heading -->
<!-- wp:paragraph --><p>Puoi esercitare i diritti di accesso, rettifica, cancellazione, limitazione, portabilità e opposizione (artt. 15–22 GDPR) scrivendo a <a href="mailto:info@gfoss.it">info@gfoss.it</a>. Hai inoltre diritto di proporre reclamo al Garante per la protezione dei dati personali.</p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p>Per i cookie utilizzati dal sito consulta la <a href="/cookie-policy/">Cookie Policy</a>.</p><!-- /wp:paragraph -->
HTML
, null );

// 8. Cookie policy (root, linkata nel footer + banner)
gfoss_seed_page( 'cookie-policy', 'Cookie Policy', 0, <<<HTML
<!-- wp:paragraph --><p>Questo sito utilizza esclusivamente <strong>cookie tecnici</strong>, necessari al suo funzionamento. Non sono utilizzati cookie di profilazione né strumenti di tracciamento pubblicitario.</p><!-- /wp:paragraph -->
<!-- wp:heading {"level":3} --><h3>Cookie tecnici</h3><!-- /wp:heading -->
<!-- wp:list --><ul>
<li><strong>Sessione / autenticazione</strong> (WordPress): mantengono attiva la sessione dei soci nell'area personale.</li>
<li><strong>gfoss_cookie_ok</strong>: ricorda che hai preso visione di questa informativa, così il banner non riappare. Durata: 12 mesi.</li>
</ul><!-- /wp:list -->
<!-- wp:paragraph --><p>I cookie tecnici non richiedono consenso preventivo (art. 122 D.Lgs. 196/2003 e linee guida del Garante).</p><!-- /wp:paragraph -->
<!-- wp:heading {"level":3} --><h3>Cookie di terze parti</h3><!-- /wp:heading -->
<!-- wp:paragraph --><p>Quando effettui un pagamento tramite <strong>PayPal</strong> vieni reindirizzato al sito del fornitore, che può impostare propri cookie secondo la sua informativa. GFOSS.it APS non ha accesso a tali cookie.</p><!-- /wp:paragraph -->
<!-- wp:heading {"level":3} --><h3>Gestione dei cookie</h3><!-- /wp:heading -->
<!-- wp:paragraph --><p>Puoi eliminare o bloccare i cookie dalle impostazioni del tuo browser. La disabilitazione dei cookie tecnici può compromettere l'accesso all'area soci.</p><!-- /wp:paragraph -->
HTML
, null );

// 8b. Pagina di ritorno dei pagamenti PayPal (return URL del gateway): ringraziamento generico.
gfoss_seed_page( 'completato', 'Pagamento completato', 0, <<<HTML
<!-- wp:heading {"level":2} --><h2>Grazie di cuore! 💚</h2><!-- /wp:heading -->
<!-- wp:paragraph --><p>Abbiamo ricevuto il tuo pagamento. Il tuo contributo sostiene concretamente lo sviluppo, la diffusione e la tutela del <strong>software geografico libero</strong> in Italia.</p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p>Riceverai a breve una <strong>conferma via email</strong>. La registrazione contabile può richiedere qualche minuto: non serve ripetere il pagamento.</p><!-- /wp:paragraph -->
<!-- wp:list --><ul>
<li>Se hai versato la <strong>quota associativa</strong>, lo stato della tua iscrizione e la tessera digitale si aggiornano automaticamente nella tua <a href="/area-soci/">area soci</a>.</li>
<li>Se hai effettuato una <strong>erogazione liberale</strong>, conserva la ricevuta PayPal: le donazioni a favore di un Ente del Terzo Settore possono dare diritto ad agevolazioni fiscali (art. 83 D.Lgs. 117/2017).</li>
</ul><!-- /wp:list -->
<!-- wp:paragraph --><p>Per qualsiasi necessità scrivici a <a href="mailto:info@gfoss.it">info@gfoss.it</a>.</p><!-- /wp:paragraph -->
<!-- wp:buttons --><div class="wp-block-buttons">
<!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="/">Torna alla home</a></div><!-- /wp:button -->
<!-- wp:button {"className":"is-style-outline"} --><div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="/area-soci/">Vai all'area soci</a></div><!-- /wp:button -->
</div><!-- /wp:buttons -->
HTML
, null );

// 9. Servizio soci: editor metadati RNDT (shortcode del plugin rndt-manager)
$area = get_page_by_path( 'area-soci', OBJECT, 'page' );
gfoss_seed_page( 'metadati-rndt', 'Metadati RNDT', $area ? (int) $area->ID : 0, <<<HTML
<!-- wp:paragraph --><p>Servizio riservato ai soci: crea, modifica ed esporta i tuoi metadati territoriali conformi al profilo <strong>RNDT 2020</strong> (INSPIRE TG / ISO 19115-19139). I metadati che crei sono personali e visibili solo a te e agli amministratori.</p><!-- /wp:paragraph -->
<!-- wp:shortcode -->
[rndt_manager]
<!-- /wp:shortcode -->
HTML
, 2 );

$area_id = $area ? (int) $area->ID : 0;

// 10. Eventi (pubblica)
gfoss_seed_page( 'eventi', 'Eventi', 0, <<<HTML
<!-- wp:paragraph --><p>Workshop, convegni e incontri di GFOSS.it APS. I soci in regola possono iscriversi direttamente da questa pagina.</p><!-- /wp:paragraph -->
<!-- wp:shortcode -->
[gfoss_eventi]
<!-- /wp:shortcode -->
HTML
, 0 );

// 10b. Progetti / crowdfunding (pubblica)
gfoss_seed_page( 'progetti', 'Progetti', 0, <<<HTML
<!-- wp:paragraph --><p>Sostieni i progetti di GFOSS.it APS: campagne di raccolta fondi per il software geografico libero e le comunità open source. Le donazioni sono acquisite dall'associazione (art. 7 D.Lgs. 117/2017).</p><!-- /wp:paragraph -->
<!-- wp:shortcode -->
[gfoss_progetti]
<!-- /wp:shortcode -->
HTML
, 0 );

// 11. Materiali soci (riservata)
gfoss_seed_page( 'materiali-soci', 'Materiali e risorse', $area_id, <<<HTML
<!-- wp:paragraph --><p>Presentazioni, template, kit di comunicazione e documentazione condivisa con i soci.</p><!-- /wp:paragraph -->
<!-- wp:shortcode -->
[gfoss_materiali]
<!-- /wp:shortcode -->
HTML
, 3 );

// 12. Convocazioni assemblea (riservata, con deleghe)
gfoss_seed_page( 'convocazioni', 'Convocazioni assemblea', $area_id, <<<HTML
<!-- wp:paragraph --><p>Le convocazioni delle assemblee dei soci, con ordine del giorno. I soci in regola possono delegare un altro socio (max 3 deleghe per socio, art. 11 dello Statuto).</p><!-- /wp:paragraph -->
<!-- wp:shortcode -->
[gfoss_convocazioni]
<!-- /wp:shortcode -->
HTML
, 4 );

// 12b. Sondaggi (riservata ai soci)
gfoss_seed_page( 'sondaggi', 'Sondaggi', $area_id, <<<HTML
<!-- wp:paragraph --><p>Sondaggi informali tra i soci. Un voto a testa; i risultati sono anonimi. (Diverso dal voto d'assemblea statutario.)</p><!-- /wp:paragraph -->
<!-- wp:shortcode -->
[gfoss_sondaggi]
<!-- /wp:shortcode -->
HTML
, 6 );

// 12c. Registro volontari (riservata al direttivo) — console front-end
gfoss_seed_page( 'registro-volontari', 'Registro volontari', $area_id, <<<HTML
<!-- wp:shortcode -->
[gfoss_registro_volontari]
<!-- /wp:shortcode -->
HTML
, 7 );

// 12d. Gestione eventi (riservata a chi pubblica) — console front-end
gfoss_seed_page( 'gestione-eventi', 'Gestione eventi', $area_id, <<<HTML
<!-- wp:shortcode -->
[gfoss_gestione_eventi]
<!-- /wp:shortcode -->
HTML
, 8 );

// 13. Mappa soci (riservata, opt-in)
gfoss_seed_page( 'mappa-soci', 'Mappa dei soci', $area_id, <<<HTML
<!-- wp:paragraph --><p>I soci che hanno attivato «Localizzami in mappa» nel proprio profilo. Attiva l'opzione dalla tua <a href="/area-soci/">area personale</a> per comparire.</p><!-- /wp:paragraph -->
<!-- wp:shortcode -->
[gfoss_mappa_soci]
<!-- /wp:shortcode -->
HTML
, 5 );

// --- Impostazioni di lettura: Home statica + archivio News ---------------
// front-page.php disegna la home; index.php disegna l'elenco News.
$home_pg = gfoss_seed_page( 'home', 'Home', 0, '<!-- wp:paragraph --><p>GFOSS.it APS — Associazione Italiana per l\'Informazione Geografica Libera.</p><!-- /wp:paragraph -->', 0 );
$news_pg = gfoss_seed_page( 'news', 'News', 0, '', 0 );
update_option( 'show_on_front',  'page' );
update_option( 'page_on_front',  $home_pg );
update_option( 'page_for_posts', $news_pg );
WP_CLI::log( "  ✓ Home statica (#$home_pg) + archivio News (#$news_pg) impostati" );

// --- Pulizia pagine obsolete (rinominate/accorpate) ----------------------
foreach ( [ 'bilanci', 'verbali', 'direttivo' ] as $old_slug ) {
    $old = get_page_by_path( $old_slug, OBJECT, 'page' );
    if ( $old ) {
        wp_delete_post( $old->ID, true );
        WP_CLI::log( "  ✗ rimossa pagina obsoleta: $old_slug (#$old->ID)" );
    }
}

// --- MENU PRINCIPALE -----------------------------------------------------
WP_CLI::log( '== Configurazione menu ==' );

$menu_name = 'Principale';
$menu = wp_get_nav_menu_object( $menu_name );
if ( ! $menu ) {
    $menu_id = wp_create_nav_menu( $menu_name );
    WP_CLI::log( "  ✓ menu '$menu_name' creato (#$menu_id)" );
} else {
    $menu_id = (int) $menu->term_id;
    WP_CLI::log( "  ↻ menu '$menu_name' esistente (#$menu_id)" );
    foreach ( wp_get_nav_menu_items( $menu_id ) as $i ) { wp_delete_post( $i->ID, true ); }
}

$home_id = (int) get_option( 'page_on_front' );
$news_id = (int) get_option( 'page_for_posts' );

// Helper: aggiunge una voce di menu (eventualmente come figlia di $parent_item) e ne ritorna l'ID.
$gfoss_add_item = static function ( int $menu_id, array $p, int $parent_item = 0 ): int {
    $args = [
        'menu-item-title'     => $p['title'],
        'menu-item-status'    => 'publish',
        'menu-item-parent-id' => $parent_item,
    ];
    if ( ! empty( $p['page'] ) ) {
        $args['menu-item-type']      = 'post_type';
        $args['menu-item-object']    = 'page';
        $args['menu-item-object-id'] = (int) $p['page'];
    } else {
        $args['menu-item-type'] = 'custom';
        $args['menu-item-url']  = $p['url'];
    }
    return (int) wp_update_nav_menu_item( $menu_id, 0, $args );
};

// Voci di primo livello. 'children_of' => le pagine figlie di quella pagina diventano sottomenu.
$pages = [
    [ 'title' => 'Home',          'page' => $home_id ?: 0, 'url' => $home_id ? '' : home_url( '/' ) ],
    [ 'title' => 'Associazione',  'page' => $assoc_id, 'children_of' => $assoc_id ],
    [ 'title' => 'News',          'page' => $news_id ?: 0, 'url' => $news_id ? '' : home_url( '/blog/' ) ],
    [ 'title' => 'Eventi',        'page' => 0, 'url' => home_url( '/eventi/' ) ],
    [ 'title' => 'Progetti',      'page' => 0, 'url' => home_url( '/progetti/' ) ],
    [ 'title' => 'Iscriviti',     'page' => 0, 'url' => home_url( '/associazione/iscrizioni-rinnovi/' ) ],
    [ 'title' => 'Area soci',     'page' => 0, 'url' => home_url( '/area-soci/' ) ],
];
foreach ( $pages as $p ) {
    $item_id = $gfoss_add_item( $menu_id, $p );
    if ( ! empty( $p['children_of'] ) && $item_id ) {
        $kids = get_pages( [
            'parent'      => (int) $p['children_of'],
            'sort_column' => 'menu_order',
            'sort_order'  => 'ASC',
        ] );
        foreach ( $kids as $kid ) {
            $gfoss_add_item( $menu_id, [ 'title' => $kid->post_title, 'page' => $kid->ID ], $item_id );
            WP_CLI::log( "    ↳ sottomenu: {$kid->post_title}" );
        }
    }
}
$locations = get_theme_mod( 'nav_menu_locations' );
$locations['primary'] = $menu_id;
$locations['footer']  = $menu_id;
set_theme_mod( 'nav_menu_locations', $locations );

// Permalink "puliti": senza questo gli URL /associazione/, /iscriviti/, ecc.
// non risolvono e WordPress ripiega sulla home. Idempotente.
if ( get_option( 'permalink_structure' ) !== '/%postname%/' ) {
    update_option( 'permalink_structure', '/%postname%/' );
    flush_rewrite_rules( true );
    WP_CLI::log( '  ✓ permalink impostati su /%postname%/' );
}

// --- Logo e favicon (dagli asset del tema, se non già impostati) ---------
if ( ! get_theme_mod( 'custom_logo' ) ) {
    $logo_id = gfoss_seed_media_from_theme( 'assets/img/logo.png', 'Logo GFOSS.it APS' );
    if ( $logo_id ) {
        set_theme_mod( 'custom_logo', $logo_id );
        WP_CLI::log( "  ✓ logo impostato (#$logo_id)" );
    }
}
if ( ! get_option( 'site_icon' ) ) {
    $fav_id = gfoss_seed_media_from_theme( 'assets/img/favicon.png', 'Favicon GFOSS.it' );
    if ( $fav_id ) {
        update_option( 'site_icon', $fav_id );
        WP_CLI::log( "  ✓ favicon impostata (#$fav_id)" );
    }
}

// Lingua italiana del backend (idempotente).
if ( get_locale() !== 'it_IT' ) {
    WP_CLI::runcommand( 'language core install it_IT --activate', [ 'exit_error' => false ] );
    WP_CLI::log( '  ✓ lingua backend impostata su Italiano (it_IT)' );
}

// Logo e favicon dagli asset del tema (uploads è gitignorato: così si applicano
// automaticamente a ogni deploy fresco). Idempotente: importa solo se non impostati.
$theme_img = get_template_directory() . '/assets/img/';
if ( ! get_theme_mod( 'custom_logo' ) && is_file( $theme_img . 'logo.png' ) ) {
    $lid = gfoss_seed_media( $theme_img . 'logo.png', 'Logo GFOSS.it APS' );
    if ( $lid ) { set_theme_mod( 'custom_logo', $lid ); WP_CLI::log( '  ✓ logo del sito impostato' ); }
}
if ( ! (int) get_option( 'site_icon' ) && is_file( $theme_img . 'favicon.png' ) ) {
    $fid = gfoss_seed_media( $theme_img . 'favicon.png', 'Favicon GFOSS.it' );
    if ( $fid ) { update_option( 'site_icon', $fid ); WP_CLI::log( '  ✓ favicon impostata' ); }
}

// Rigenera le regole di rewrite (necessario per i CPT con URL propri, es. progetti).
flush_rewrite_rules( false );

WP_CLI::success( 'Seed completato. Visita il sito.' );
