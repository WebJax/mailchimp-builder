# Mailchimp Builder WordPress Plugin

Et WordPress plugin til automatisk at generere og sende nyhedsbreve baseret på seneste indlæg og kommende arrangementer fra The Events Calendar plugin.

## Funktioner

- **Automatisk nyhedsbrev generering** baseret på seneste WordPress indlæg
- **Integration med The Events Calendar** plugin for arrangementer
- **Udvalgte billeder som headers** for indlæg og arrangementer
- **Fleksibel datofiltrering** for arrangementer
- **Mailchimp API integration** for kampagne oprettelse og afsendelse
- **Responsivt nyhedsbrev design** med moderne HTML/CSS
- **Fuldt konfigurerbart** med admin interface
- **Preview funktion** før afsendelse
- **Dansk lokalisering**

## Installation

1. Upload plugin mappen til `/wp-content/plugins/mailchimp-builder/`
2. Aktiver pluginet gennem 'Plugins' menuen i WordPress
3. Gå til 'Mailchimp Builder' i admin menuen for at konfigurere

## Konfiguration

### Mailchimp Setup

1. **API Nøgle**: Log ind på din Mailchimp konto og gå til Profile icon > Profile > Extras dropdown > API keys
2. **Liste ID**: Find ID'et på den liste du vil sende til under Audience > Settings > Audience name and defaults

### Plugin Indstillinger

#### Grundlæggende Indstillinger
- **API Nøgle og Liste ID**: Forbind til din Mailchimp konto
- **Inkluder Indhold**: Vælg om du vil inkludere indlæg og/eller arrangementer
- **Antal Indlæg/Arrangementer**: Bestem hvor mange der skal inkluderes (1-20)
- **Uddrag Længde**: Hvor mange tegn fra indholdet der skal vises (50-500)

#### Nye Funktioner
- **Arrangementer Til Dato**: Vælg en slutdato for arrangementer (lad være tom for alle kommende)
- **Billede Indstillinger**: Inkluder udvalgte billeder som headers for indlæg og arrangementer

## Brug

1. Konfigurer dine Mailchimp indstillinger
2. Vælg hvilke typer indhold der skal inkluderes
3. Indstil dato-rammer og billede-præferencer
4. Klik på "Generer Nyhedsbrev" for at oprette indhold
5. Gennemse preview af nyhedsbrevet
6. Indtast emne og klik "Send Nyhedsbrev"

## Billede Funktioner

### Understøttede Billeder
- **Udvalgte billeder** (Featured Images) fra indlæg og arrangementer
- Automatisk skalering til passende størrelse for email
- **Responsivt design** der fungerer på alle enheder
- Elegant styling med afrundede hjørner og skygger

### Billede Optimering
- Maksimal bredde på 600px for email kompatibilitet
- Automatisk højde-beregning for at bevare aspect ratio
- Fallback hvis billede ikke er tilgængeligt

## Dato Filtrering

### Arrangement Dato Kontrol
- **Standard**: Viser alle kommende arrangementer
- **Brugerdefineret slutdato**: Begræns til arrangementer inden for en bestemt periode
- **Automatisk forslag**: 3 måneder frem som standard
- **Fleksibel periode**: Vælg præcis den periode der passer til dit nyhedsbrev

## Krav

- WordPress 5.0 eller nyere
- PHP 7.4 eller nyere
- Aktiv Mailchimp konto
- The Events Calendar plugin (valgfri - kun hvis du vil inkludere arrangementer)

## Nyhedsbrev Design

Pluginet genererer et responsivt HTML nyhedsbrev med:

- Header med sidens navn og dato
- **Billede headers** for hvert indlæg/arrangement (hvis aktiveret)
- Sektion for seneste indlæg med uddrag og links
- Sektion for kommende arrangementer med datoer og lokationer
- **Responsive billeder** der tilpasser sig alle skærmstørrelser
- Footer med links og kontaktinfo
- Moderne CSS styling der virker på alle email klienter

## Sikkerhed

- Alle bruger input saniteres
- AJAX requests benytter WordPress nonces
- Kun administratorer kan bruge pluginet
- API nøgler gemmes sikkert i WordPress database
- Billedstørrelser valideres og optimeres

## Pro Tips

- **Billede kvalitet**: Brug høj-kvalitets udvalgte billeder for bedste resultat
- **Dato planlægning**: Indstil arrangement slutdato til at matche dit nyhedsbrev interval
- **Test først**: Send altid til en test liste før hovedudsendelse
- **Konsistens**: Brug samme billede-dimensioner for bedst visuel effekt

## Support

For support eller fejlrapporter, kontakt plugin udvikleren.

## Changelog

### Version 1.0.0
- Initial release
- Grundlæggende nyhedsbrev generering
- Mailchimp API integration
- The Events Calendar integration
- Admin interface
- Dansk lokalisering

### Version 1.1.0 (Aktuel)
- **NYT**: Udvalgte billeder som headers
- **NYT**: Fleksibel datofiltrering for arrangementer
- **FORBEDRET**: Responsivt design for billeder
- **FORBEDRET**: Bedre admin interface
- **FORBEDRET**: Automatisk skalering af billeder
