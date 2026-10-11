# Piano: calendar-bundle

## Obiettivo

Un componente calendario riutilizzabile in più applicazioni Symfony. Il componente:
- genera l'HTML lato server con Symfony UX (LiveComponent);
- usa Stimulus solo per le interazioni;
- non conosce il dominio applicativo.

Deve poter mostrare e gestire dati temporali di contesti diversi, come turni di lavoro, prenotazioni di posti e lezioni.

Il calendario è un **componente di presentazione**, non un dominio DDD autonomo. Le regole di business restano nei moduli di dominio delle applicazioni.

## Decisioni prese

| Tema | Decisione |
|---|---|
| Rendering | Lato server con un LiveComponent; JavaScript non disegna mai la griglia |
| Implementazione UI | Scritta da zero, senza librerie calendario JS |
| Viste | Mese, settimana, giorno, anno, lista, timeline, colonne per risorsa |
| Packaging | Bundle autonomo sul modello di gridview-bundle |
| CSS | Namespace `cal-*`, nessun framework CSS, temi opzionali |
| API Platform | Non è una dipendenza: bridge opzionale |
| Configuratore UI | Nucleo "scoped settings" di setting-bundle (vedi `setting-bundle/docs/plan-scoped-settings.md`) |
| Regole di prenotazione | Pacchetto separato `booking-policy` (piano da scrivere); il calendario ne vede solo i risultati |

## 1. Packaging

- **Dipendenze**: `symfony/ux-live-component`, `symfony/ux-twig-component`, `symfony/stimulus-bundle`, `fedale/setting-bundle` (solo le interfacce del nucleo scoped).
- **Nessuna dipendenza** da Doctrine, API Platform o framework CSS.
- **CSS**: namespace `cal-*` e custom properties per colori, spaziature e dimensioni. Temi opzionali tramite mappa di chiavi di classe, come in gridview-bundle.
- **Stimulus**: controller propri, distribuiti come asset del bundle.
- **Template**: sovrascrivibili tramite blocchi Twig.
- **Configurazione**: YAML `fedale_calendar` (`defaults`, `calendars.<id>`), più la rotta del configuratore UI da importare.
- Codice, commenti e documentazione del bundle in inglese.

## 2. Definizioni di calendario e configurazione

Ogni calendario è una **definizione con un id**, che fa anche da scope del configuratore UI:

- **Classe di definizione**: `AbstractCalendar`, da estendere, oppure `CalendarDefinitionInterface`. Contiene `id()`, le fonti e `viewConfig()`. Viene configurata automaticamente con il tag `fedale_calendar.calendar`, ed è l'equivalente di `AbstractGridController` in gridview.
- **Uso nel template**: `<twig:Calendar id="shifts" />`. Il componente risolve le opzioni dalla definizione.
- **`CalendarRegistry`**: un locator lazy delle definizioni, che produce un `CalendarDescriptor` (id, etichetta, opzioni).

**Albero delle opzioni**:
- `display`: viste abilitate e vista predefinita, primo giorno della settimana, giorni nascosti, durata dello slot, orario minimo e massimo, formato dell'ora, numeri di settimana, densità, orari lavorativi, locale;
- `behavior`: selezione, trascinamento, ridimensionamento, "+N altri";
- `integration`: fonti, timezone resolver.

**Precedenza**, dalla più bassa alla più alta:
1. default del bundle e YAML;
2. `viewConfig()` della definizione;
3. impostazione UI globale;
4. impostazione UI del calendario;
5. LiveProp legate all'URL (`view`, `date`).

Ogni impostazione sostituisce solo la foglia dell'albero a cui punta. Un valore che il calendario non può rispettare viene ignorato (`isApplicable`).

## 3. Componente UI

### 3.1 Stato e navigazione

Il calendario è un **LiveComponent**. Il suo stato è fatto di LiveProp:
- `id`: la definizione;
- `view`: la vista attiva;
- `date`: la data di riferimento;
- `sources`: le fonti attive;
- `filters`: filtri opachi, definiti dalle fonti;
- `timezone`.

`view`, `date` e `filters` sono legate all'URL (`url: true`), così ogni vista ha un link diretto e funzionano i pulsanti avanti/indietro del browser. La navigazione (precedente, successivo, oggi, cambio vista) passa per LiveAction e un nuovo rendering lato server.

### 3.2 Viste

| Famiglia | Viste | Struttura |
|---|---|---|
| Griglia di giorni | Mese, Anno | Celle-giorno. Nel mese: barre per gli eventi su più giorni e "+N altri". Nell'anno: 12 mini-mesi con indicatori di densità; il clic su un giorno apre la vista giorno |
| Griglia oraria | Giorno, Settimana, Colonne per risorsa | Asse verticale delle ore e riga "tutto il giorno". Le colonne sono giorni oppure risorse, con eventuale combinazione giorni × risorse |
| Timeline | Timeline | Una riga per risorsa, asse orizzontale del tempo, eventi disposti su corsie |
| Lista | Lista | Agenda raggruppata per giorno |

- Ogni vista è una classe PHP (`CalendarViewInterface`) che, dalla query e dagli eventi, produce un **view model** per il template Twig. Tutta la logica di layout sta in PHP ed è testabile con unit test.
- **Motori di layout condivisi**:
  - `OverlapLayout`: assegna colonne e corsie agli eventi sovrapposti; serve alla griglia oraria (in verticale) e alla timeline (in orizzontale);
  - `WeekRowLayout`: dispone le barre degli eventi su più giorni nelle righe del mese.
- **Posizionamento**: il server scrive posizione ed estensione come custom properties CSS (`--cal-start`, `--cal-span`, `--cal-lane`, `--cal-lanes`), che il CSS usa con CSS Grid o percentuali. Il posizionamento non dipende dal tema.
- Il layout viene calcolato **nel fuso di visualizzazione**: nella griglia oraria esistono giorni da 23 e 25 ore, per il cambio dell'ora legale.

### 3.3 Interazione client (Stimulus)

JavaScript **non disegna** nulla. Legge gli attributi `data-*` generati dal server (`data-cal-slot-start`, `data-cal-resource`, `data-cal-event`) e li trasforma in intenti.

- `cal-select`: selezione di un intervallo trascinando sugli slot.
- `cal-drag`: spostamento e ridimensionamento, con aggancio allo slot e anteprima ottimistica.
- `cal-popover`: dettaglio dell'evento e "+N altri".
- `cal-keyboard`: navigazione da tastiera, con alternative da tastiera a spostamento e ridimensionamento.
- `calendar-ui-settings`: apre il configuratore.

**Accessibilità**: ruoli ARIA da griglia, focus mantenuto dopo il nuovo rendering, etichette testuali per gli eventi.

## 4. Contratto di lettura

### 4.1 Modelli

```php
final readonly class CalendarEvent
{
    public function __construct(
        public string $id,                 // unique within its source
        public string $title,
        public \DateTimeImmutable $start,  // UTC
        public \DateTimeImmutable $end,    // UTC, exclusive
        public bool $allDay = false,       // date-only semantics, end exclusive
        public array $resourceIds = [],
        public ?string $type = null,
        public ?string $status = null,
        public EventLayer $layer = EventLayer::Foreground,
        public EventVisibility $visibility = EventVisibility::Full,
        public EventInteractions $interactions = new EventInteractions(),
        public ?string $version = null,    // optimistic concurrency token
        public EventStyle $style = new EventStyle(),
        public array $hints = [],          // display-only facts, e.g. "bookable from", "3 seats left"
        public ?string $url = null,
        public array $data = [],           // opaque, never interpreted by the UI
    ) {}
}

final readonly class CalendarResource
{
    public function __construct(
        public string $id,
        public string $title,
        public ?string $groupId = null,
        public int $order = 0,
        public EventStyle $style = new EventStyle(),
        public array $data = [],
    ) {}
}
```

- **`EventLayer`**: `Foreground` è l'evento normale, `Background` una fascia di sfondo (disponibilità, riposi, indisponibilità).
- **`EventVisibility`**: quanto dettaglio vede l'utente corrente. `Full` mostra tutto; `BusyOnly` mostra solo l'intervallo, con un titolo generico come "Occupato"; `Hidden` significa che la fonte esclude l'evento.
- **`EventInteractions`**: quali interazioni la UI abilita (`movable`, `resizable`, `clickable`, `deletable`). Le calcola la fonte, anche in base ai permessi dell'utente.
- **`EventStyle`**: colore, classe CSS, icona.
- **`hints`**: informazioni solo da mostrare, già calcolate dalla fonte (anche a partire da booking-policy). La UI le visualizza senza interpretarle come regole.

`CalendarQuery` contiene:
- `DateRange`, un intervallo semiaperto `[start, end)`;
- il timezone di visualizzazione;
- un filtro sulle risorse;
- filtri opachi, definiti dalla fonte.

### 4.2 Fonti

```php
interface EventSourceInterface
{
    /** @return iterable<CalendarEvent> */
    public function getEvents(CalendarQuery $query): iterable;
}

interface ResourceSourceInterface
{
    /** @return iterable<CalendarResource> */
    public function getResources(CalendarQuery $query): iterable;
}
```

- Le fonti si registrano con l'attributo `#[AsCalendarSource('shifts')]` e vengono raccolte in un registry. Una stessa vista può sovrapporre più fonti.
- **Ricorrenze**: le espande la fonte nell'intervallo richiesto. La UI non conosce le RRULE.
- **Fusi orari**: il contratto usa UTC; la conversione avviene in fase di rendering. Il fuso si ottiene da un `TimezoneResolverInterface`, sostituibile.
- **Sicurezza**: la fonte filtra i dati in base all'utente e calcola visibilità e interazioni.
- **Interfaccia opzionale `EventDensitySourceInterface`**: restituisce conteggi aggregati per giorno, per la vista anno.

## 5. Contratto di scrittura (intenti)

Il calendario non scrive: produce **intenti** generici e li passa all'handler della fonte competente.

| Intento | Contenuto |
|---|---|
| `SelectRange` | intervallo, risorsa, allDay |
| `MoveEvent` | evento, nuovo inizio e fine, nuova risorsa, versione attesa |
| `ResizeEvent` | evento, nuovi estremi, versione attesa |
| `ClickEvent` | evento |
| `DeleteEvent` | evento, versione attesa |

```php
interface IntentHandlerInterface
{
    public function handle(CalendarIntent $intent): IntentResult;
}
```

`IntentResult` può essere:
- `Accepted`, con un messaggio facoltativo;
- `Rejected`, con motivo (`conflict`, `validation`, `forbidden`) e messaggio;
- `OpenUrl`, per aprire un form di dominio in un Turbo Frame o in un modale (tipicamente la creazione da `SelectRange`).

**Flusso**:
1. Stimulus mostra l'anteprima ottimistica.
2. Una LiveAction invia l'intento.
3. Il registry individua l'handler della fonte e lo chiama.
4. Il componente viene renderizzato di nuovo.

Lo stato vero viene sempre dal server. Se l'intento viene rifiutato, l'evento torna nella posizione originale con un messaggio, senza bisogno di una logica di annullamento lato client.

- **Concorrenza**: l'handler confronta la versione attesa con quella corrente. Se non coincidono, risponde `Rejected(conflict)`.
- **Estendibilità**: prima di inviare un intento il client emette un evento DOM annullabile (`cal:intent`).
- **Fonti in sola lettura**: una fonte senza handler è automaticamente di sola lettura.

## 6. Configuratore UI

Usa il nucleo "scoped settings" di setting-bundle, con il namespace `calendar.ui` e la catena `[calendar:<id>, global]`.

- **Impostazioni**: una classe per impostazione, che estende la definizione del nucleo e aggiunge `optionPath()`, `supportsCalendar(CalendarDescriptor)` e `isApplicable($value, $options)`, come in gridview.
- **Flusso Hotwire**: il corpo del modale è un `<turbo-frame>` e il salvataggio segue lo schema Post/Redirect/Get. Dopo il salvataggio, il controller Stimulus emette un evento Live (`calendar:settingsChanged`). Ogni componente calendario lo ascolta con `#[LiveListener]` e si renderizza di nuovo.
- **Impostazioni fornite dal bundle** (indicative; da rivedere nella fase 5 in base ai tipi disponibili nella v1 del nucleo: `Bool`, `Int`, `Float`, `String`, `Enum`):

| Impostazione | `optionPath` | Quando si applica |
|---|---|---|
| Vista predefinita | `display.views.default` | solo se la vista è tra quelle abilitate; offerta solo se il calendario ne abilita più di una |
| Primo giorno della settimana | `display.firstDay` | sempre |
| Weekend visibili | `display.hiddenDays` | viste con griglia di giorni od oraria |
| Durata dello slot (minuti, `Enum`) | `display.slotDuration` | solo con viste orarie o timeline |
| Orario minimo e massimo | `display.slotMinTime` / `slotMaxTime` | come sopra |
| Formato dell'ora | `display.timeFormat` | sempre |
| Densità | `display.density` | sempre |

## 7. Integrazione con booking-policy

`booking-policy` è un pacchetto separato e opzionale: politiche di prenotazione configurabili per scope (globale → tipo di evento → risorsa → serie → occorrenza), da applicare nei moduli di dominio. Il calendario **non dipende** da booking-policy: ne vede i risultati attraverso il contratto, cioè `EventInteractions`, `EventVisibility` e `hints`, che la fonte calcola. Il piano di booking-policy è da scrivere a parte.

## 8. Moduli di dominio e adapter

I moduli di dominio (turni, posti, lezioni) restano responsabili delle proprie regole e possono adottare DDD secondo le loro esigenze. Non si impone un'entità universale come `Appointment`.

Ogni applicazione implementa `EventSourceInterface`, `ResourceSourceInterface` e, facoltativamente, `IntentHandlerInterface`:

| Modulo | Risorse | Eventi | Background |
|---|---|---|---|
| Turni | dipendenti | turni assegnati | disponibilità, riposi |
| Posti | posti | prenotazioni | posti non disponibili |
| Lezioni | aule o docenti | lezioni | indisponibilità delle aule |

## 9. API Platform (opzionale)

Bridge attivato solo se il pacchetto è installato, oppure in un pacchetto separato:
- espone le fonti come feed JSON in sola lettura, per app mobili o client esterni;
- può mappare gli intenti sulle operazioni di scrittura dei domini.

Come estensione futura è possibile un export iCal.

## 10. Separazione delle responsabilità

- **Componente UI**: rendering delle viste, layout, navigazione, interazione, emissione degli intenti.
- **Contratto di lettura**: eventi, risorse, query.
- **Contratto di scrittura**: intenti ed esiti.
- **Configuratore UI**: preferenze di visualizzazione, tramite setting-bundle.
- **booking-policy**: regole di prenotazione configurabili, applicate dai domini.
- **Moduli di dominio**: regole di business, validazione definitiva, concorrenza.
- **Adapter**: mappatura tra dominio e contratto e traduzione degli intenti in comandi.
- **API Platform**: esposizione opzionale.

## 11. Fasi

Le fasi 2 e 3 sono state invertite dopo la fase 1: la timeline riusa quasi tutto il lavoro del mese (corsie, "+N altri", fasce di sfondo), mentre la griglia oraria richiede un algoritmo nuovo (colonne affiancate dentro lo stesso giorno). La vista a colonne per risorsa è una griglia oraria, quindi resta nella fase della griglia.

1. **Fondamenta** (completata): definizioni di calendario, albero delle opzioni e precedenza (senza configuratore UI), contratto di lettura, registry delle fonti, guscio del LiveComponent, navigazione, viste mese e lista, verifica delle performance (vedi sotto).
2. **Timeline e risorse** (completata):
   - viste come **tipo + durata**, sul modello di FullCalendar: `timelineDay`, `timelineWeek` e `timelineMonth` sono varianti della stessa vista `timeline`; anche la lista diventa `listDay` / `listWeek` / `listMonth`, al posto di `display.list.range`. Formato scelto: `display.views.variants`, una mappa aperta `nome => ['type' => ..., parametri, 'label' => ...]`; ogni tipo valida i propri parametri (`validateVariant()`) e le varianti predefinite si possono modificare chiave per chiave;
   - contratto delle risorse: `parentId` in `CalendarResource` per le risorse annidate, colonne dell'area risorse configurabili (`title` o un campo di `data`), raggruppamento per campo;
   - **misura delle performance** con circa 50 risorse in calendar-demo, prima di fissare la struttura (componente unico, componenti figli per riga o paginazione delle risorse);
   - vista timeline: corsie su un asse di tempo continuo, "+N altri" per riga, intestazioni su due livelli (per esempio settimana sopra, ore sotto), intestazioni fisse e scroll orizzontale in CSS;
   - gruppi comprimibili e linea dell'ora corrente (posizione dal server, aggiornata ogni minuto da un controller Stimulus minimo). **Scostamento**: la compressione non è lato client ma una LiveProp (`collapsed`) con un'azione: lo stato sopravvive alla navigazione e ai re-render (un `<details>` o un controller verrebbero riportati allo stato del server dal morphing di LiveComponent) e le righe compresse non vengono nemmeno renderizzate;
   - **toolbar configurabile** (`display.toolbar` con zone `start` / `center` / `end` e token, sul modello dei token di layout di gridview) e **pulsanti personalizzati** con azione URL (anche in Turbo Frame o modale), evento DOM o LiveAction.
3. **Griglia oraria** (completata): tipo di vista `timeGrid` con le varianti `timeGridDay`, `timeGridWeek`, `resourceTimeGridDay` e `resourceTimeGridWeek`.
   - `ColumnLayout` (il `OverlapLayout` del piano): gli eventi sovrapposti si affiancano dentro la colonna del giorno, e ogni evento si allarga a destra dove le colonne vicine sono libere;
   - gli eventi a orario che attraversano la mezzanotte si dividono sulle colonne dei giorni; quelli "tutto il giorno" vanno nella riga in alto, con lo stesso layout a corsie del mese;
   - colonne per risorsa: risorse sopra, i loro giorni sotto (l'ordine contrario, giorni sopra e risorse sotto, non è implementato); `groupBy` non si applica alla griglia;
   - **orari lavorativi** (`display.businessHours`): solo un aiuto visivo, scuriscono le ore di chiusura nella griglia e nella timeline;
   - il controller Stimulus `cal-timeline` è diventato `cal-now`, con un parametro per l'asse (orizzontale per la timeline, verticale per la griglia).
4. **Interazione** (completata): selezione, spostamento (anche su un'altra risorsa), ridimensionamento, click e cancellazione, nella vista mese, nella riga "tutto il giorno", nella griglia oraria e nella timeline.
   - gli intenti e l'`IntentHandlerInterface` sono come da piano (sezione 5); `IntentDispatcher` scarta prima dell'handler ciò che il calendario può già sapere: evento non più a schermo, interazione non abilitata, versione cambiata, fine prima dell'inizio;
   - il controller `cal-interact` legge solo la geometria generata dal server (`data-cal-track`, `data-cal-axis`) e calcola orari "da orologio da parete" nel fuso di visualizzazione, come il layout PHP;
   - l'anteprima del trascinamento viene tolta all'arrivo della risposta: LiveComponent conserva le modifiche fatte dagli script tra un rendering e l'altro;
   - gli argomenti delle azioni Live sono tutti stringhe (il cast dei booleani tra JSON e `LiveArg` non è affidabile);
   - limiti noti: gli eventi dentro un popover "+N altri" non si trascinano; spostamento e ridimensionamento da tastiera sono rimandati all'accessibilità della fase 5.
5. **Completamento** (completata):
   - **vista anno**: 12 mini-mesi con la densità per giorno (0-4, relativa al giorno più pieno); un giorno si apre nella vista giornaliera più dettagliata abilitata. La densità si conta dagli eventi: `EventDensitySourceInterface` (conteggi forniti dalla fonte) non è stata implementata, perché con ~6000 eventi l'anno il conteggio resta sotto i 100 ms in sviluppo; va ripresa se servirà;
   - **configuratore UI** sul nucleo di setting-bundle (namespace `calendar.ui`, scope `global` e `calendar:<id>`), sul modello di gridview: impostazioni vista predefinita, primo giorno, weekend, formato dell'ora, numeri di settimana, durata degli slot. **Scostamenti**: la finestra è un `<dialog>` nativo caricato con `fetch` (non un Turbo Frame, per non richiedere Turbo), si apre dalla voce di toolbar `settings` e, dopo il salvataggio, il controller chiede a ogni calendario della pagina di rifare il rendering (nessun `#[LiveListener]`); `apply()` sostituisce `optionPath()` nell'interfaccia, perché alcune impostazioni toccano più foglie;
   - **temi**: mappa di chiavi di classe per i controlli (`default`, `bootstrap5`, temi dell'applicazione in YAML), come gridview;
   - **accessibilità**: spostamento e ridimensionamento da tastiera, focus mantenuto dopo il rendering, `aria-description` sugli eventi interattivi. Restano da fare i ruoli ARIA di griglia con navigazione a frecce tra le celle;
   - **i18n**: inglese, italiano, francese, tedesco e spagnolo.
6. **Extra**: bridge API Platform (completato). L'applicazione demo esiste già (calendar-demo) e cresce insieme alle fasi.
   - in `src/Bridge/ApiPlatform/`, **opt-in** (`fedale_calendar.api.enabled`, spento di default) e solo con ApiPlatformBundle abilitato: installare API Platform non deve esporre nulla per effetto collaterale. Si possono limitare i calendari esposti (`api.calendars`, gli altri rispondono 404) e richiedere attributi di sicurezza per lettura e scrittura (`api.read_attribute`, `api.write_attribute`, verificati con `isGranted(attributo, id calendario)`); le risorse sono DTO (calendari, eventi, risorse, selezioni), mai le entità dell'applicazione;
   - **lettura** attraverso gli stessi collector della UI, quindi con le stesse regole di visibilità (eventi `Hidden` esclusi, `BusyOnly` senza titolo né url, `data` mai esposto);
   - **scrittura** con gli stessi intenti della UI (`move`, `resize`, `delete`, `selections`), passando da `IntentDispatcher::dispatchById()`; gli esiti diventano stati HTTP (200, 409, 422, 403); la versione arriva in `If-Match` o nel body;
   - nuova interfaccia opzionale **`EventFinderInterface`** (una fonte restituisce un evento dato l'id): via API non c'è una pagina a schermo da cui ritrovare l'evento, e con il finder le scritture hanno gli stessi controlli preliminari della UI; senza, decide solo l'handler;
   - `IntentDispatcher` ora rifiuta gli intenti verso fonti che non appartengono al calendario: dalla UI era implicito, via API va controllato;
   - `DELETE` risponde 204 senza il messaggio dell'handler (comportamento standard di API Platform);
   - da fare se servirà: export iCal.

## 12. Da approfondire durante le fasi

- **Performance: verificata per mese e lista (fine fase 1).** Misure su calendar-demo, ambiente prod, PHP 8.4 con opcache, mediana di 7 richieste, HTML non compresso:

  | Calendario | Eventi nel mese | Pagina | Navigazione Live | HTML |
  |---|---|---|---|---|
  | lessons (lista) | 6 | 14 ms | 12 ms | 59 KB |
  | shifts | ~240 | 34 ms | 34 ms | 175 KB |
  | overview (3 fonti) | ~380 | 48 ms | 52 ms | 214 KB |
  | stress | ~590 | 84 ms | 93 ms | 341 KB |
  | stress ×2 | ~1180 | 148 ms | 167 ms | 551 KB |

  **Decisione: il calendario resta un unico LiveComponent** per mese e lista. Il costo cresce in modo lineare e i componenti figli non servono. Prima delle misure i tempi erano circa il doppio: sono stati eliminati il ricalcolo delle date nel comparatore e nel layout, e la creazione di un `IntlDateFormatter` per ogni data formattata. Restano due osservazioni:
  - con molti eventi per giorno, il peso dell'HTML viene quasi tutto dai popover "+N altri", che ripetono ogni evento del giorno (nello stress, 591 voci su 681 elementi). Se diventa un problema, il contenuto del popover può essere caricato al momento dell'apertura;
  - la timeline con molte risorse è stata misurata nella fase 2 (stesse condizioni):

    | Caso | Pagina | Navigazione Live | HTML |
    |---|---|---|---|
    | 14 sale, giorno / settimana / mese | 15 / 22 / 48 ms | 13 / 20 / 44 ms | 85 / 124 / 269 KB |
    | 50 sale, giorno / settimana | 20 / 41 ms | 16 / 38 ms | 136 / 271 KB |
    | 50 sale, mese (~1800 eventi) | 126 ms | 119 ms | 774 KB |

    La griglia oraria, misurata nella fase 3, è leggera: settimana con ~110 eventi 26 ms, 50 colonne-risorsa 24 ms, HTML sotto i 125 KB. Le misure si ripetono con `bin/benchmark` di calendar-demo.

    Con i dati reali di calendar-demo (SQLite via Doctrine, ~7.500 prenotazioni e ~4.900 appuntamenti) i tempi restano dello stesso ordine: giorno 23-34 ms, settimana con 54 sale 58 ms, mese con 54 sale (~3.800 prenotazioni) 178 ms per **2,2 MB** di HTML. È il caso in cui il peso della navigazione Live diventa il limite reale: la paginazione delle risorse o il rendering parziale per riga sono il primo intervento se un'applicazione arriva a questi volumi.

    **Decisione: anche la timeline resta un unico LiveComponent**, senza componenti figli per riga. Il punto debole è il peso della navigazione Live nel caso più grande (tutto l'HTML a ogni passo, ~710 KB non compressi). Se servirà, le leve sono la paginazione delle risorse o un rendering parziale per riga; oltre il centinaio di risorse va rimisurato.
- **View model**: definiti vista per vista, nella fase in cui la vista viene implementata.
- **Zoom della timeline**: nella fase 2. **Combinazione giorni × risorse** nelle colonne per risorsa: nella fase 3.
- **Trascinamento tra contesti diversi** (evento su più giorni nel mese, cambio di risorsa nella timeline): nella fase 4. Il calcolo dello slot di arrivo lato client si basa solo su attributi generati dal server.
- **Eventi assegnati a più risorse**: ripetuti in ogni riga o colonna; dettagli nella fase 2 (timeline) e nella fase 3 (colonne per risorsa).
- **Opzioni del configuratore UI**: nella fase 5.
