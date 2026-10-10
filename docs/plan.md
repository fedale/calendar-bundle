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

1. **Fondamenta**: definizioni di calendario, albero delle opzioni e precedenza (senza configuratore UI), contratto di lettura, registry delle fonti, guscio del LiveComponent, navigazione, viste mese e lista. **Verifica delle performance** prima di chiudere la fase (vedi sotto).
2. **Griglia oraria**: viste giorno e settimana, `OverlapLayout`, riga "tutto il giorno", orari lavorativi. View model della griglia oraria.
3. **Risorse**: colonne per risorsa (inclusa la combinazione giorni × risorse) e timeline (con i livelli di zoom), raggruppamento delle risorse.
4. **Interazione**: selezione, spostamento, ridimensionamento, intenti, gestione dei rifiuti e dei conflitti.
5. **Completamento**: vista anno con densità, configuratore UI (dipende dal nucleo di setting-bundle), temi, accessibilità, i18n.
6. **Extra**: bridge API Platform e applicazione demo con i tre domini di esempio.

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
  - la timeline con molte risorse (per esempio 50 risorse × 1 mese) va misurata all'inizio della fase 3, prima di fissarne la struttura: lì potrebbero servire componenti figli per riga o la paginazione delle risorse.
- **View model**: definiti vista per vista, nella fase in cui la vista viene implementata.
- **Combinazione giorni × risorse e zoom della timeline**: nella fase 3.
- **Trascinamento tra contesti diversi** (evento su più giorni nel mese, cambio di risorsa nella timeline): nella fase 4. Il calcolo dello slot di arrivo lato client si basa solo su attributi generati dal server.
- **Eventi assegnati a più risorse**: ripetuti in ogni riga o colonna; dettagli nella fase 3.
- **Opzioni del configuratore UI**: nella fase 5.
