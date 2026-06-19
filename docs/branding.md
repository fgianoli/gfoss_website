# GFOSS.it — Identità visiva 2026

## Logo

Il marchio storico è composto da:

- Simbolo: **sole arancione** stilizzato (riferimento orografico/cartografico)
- Wordmark: **gfoss.it** in colore blu acqua
- Endorsement: badge **OSGeo·IT Local Chapter** (rosa dei venti + verde)

Il logo va inserito in `wp-content/themes/gfoss-2026/assets/img/logo.svg` (versione vettoriale principale) e in `logo.png` 2x come fallback. Variante negativa per footer scuro: `logo-inverse.svg`.

## Palette

Derivata dal logo, con neutri moderni e ampia leggibilità.

| Token             | HEX       | Uso                                    |
|-------------------|-----------|----------------------------------------|
| `--gf-blue-700`   | `#1A6FA0` | Header, CTA primaria, link             |
| `--gf-blue-500`   | `#2BA5D9` | Highlight, hover, badge                |
| `--gf-blue-100`   | `#E6F3FA` | Backgrounds soft                       |
| `--gf-orange-500` | `#F39200` | Accenti, tag "novità", icone notifiche |
| `--gf-green-600`  | `#5DA34D` | Stati positivi (quota in regola)       |
| `--gf-red-600`    | `#C0392B` | Stati negativi (quota scaduta)         |
| `--gf-ink-900`    | `#0F2330` | Testo principale                       |
| `--gf-ink-600`    | `#4A5C6A` | Testo secondario                       |
| `--gf-paper`      | `#FAFBFC` | Sfondo pagina                          |
| `--gf-line`       | `#E2E8EC` | Bordi, separatori                      |

## Typography

- **Display & headings**: [Manrope](https://fonts.google.com/specimen/Manrope) (400/600/700) — geometrica, moderna, ottima leggibilità.
- **Body**: [Inter](https://fonts.google.com/specimen/Inter) (400/500/600).
- **Mono / dati**: [JetBrains Mono](https://fonts.google.com/specimen/JetBrains+Mono) per IBAN, codici fiscali, ID transazioni.

I font sono self-hosted (vedi `assets/fonts/`) per GDPR, non caricati da Google Fonts CDN.

## Iconografia

Set [Lucide](https://lucide.dev/) (open source, MIT). Stile linea sottile coerente con il logo.

Icone tematiche ricorrenti: `map`, `compass`, `globe`, `layers`, `code-2`, `users`, `calendar`, `file-text`.

## Pattern decorativi

Un sottile pattern **grid topografica** generato con `<svg>` come background della hero. Definito in `assets/css/pattern.css`. Opacità ~6% così non disturba la lettura.

## Tono di voce

Inclusivo, chiaro, tecnico ma non gergale. Sempre in italiano. Quando si cita "GFOSS.it" usare il punto, non maiuscolo. Per il nome legale completo: **Associazione Italiana per l'Informazione Geografica Libera APS**.

## Componenti UI ricorrenti

- **Card socio** (area personale): foto + ruolo + stato quota + scadenza
- **Stato quota**: chip `IN REGOLA` (verde) / `IN SCADENZA` (arancio) / `SCADUTA` (rosso)
- **Tessera digitale**: layout orizzontale 85×54 mm, logo + numero socio + QR
- **Hero homepage**: sole arancione che "sorge" sul pattern topografico, claim sotto
