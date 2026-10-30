# SkyGuard

SkyGuard je web aplikacija za upravljanje dronovima i lovnim kamerama te podršku odlučivanju u nadzoru državne granice. Razvijena je kao projekt 2. godine diplomskog studija Primjena umjetne inteligencije. Cilj aplikacije je povezivanje evidencije letova i detekcija s prostornom analizom, strojnim učenjem i AI asistentom.

Sustav je dizajniran da analizira povijesne detekcije i GPX putanje letova kako bi prepoznao žarišta aktivnosti, procijenio mogućnost budućih detekcija i predložio područja za pojačanje ili smanjenje nadzora.

Funkcionalnosti:
+ Upravljanje flotom: evidencija dronova, letova s GPX putanjom i grafom visine te kvarova i održavanja.
+ Lovne kamere: evidencija lokacija i promjena, uz geografska ograničenja postavljanja.
+ Detekcije: unos osoba, skupina, vozila i ostalih opažanja iz različitih izvora, uz filtriranje po vrsti, izvoru, vremenu i postaji.
+ Organizacijska struktura: policijske uprave i granične postaje, korisničke uloge i ograničavanje pristupa podacima.
+ Analitika i izvješća: statistika detekcija i letova, vremenski trendovi, mjesečna izvješća i interaktivne karte.
+ ML analiza: DBSCAN žarišta, usporedba pet modela strojnog učenja, predikcije po zonama i prikaz preporuka nadzora.
+ AI asistent: razgovor na hrvatskom jeziku uz dohvat podataka iz sustava i prikaz rezultata na karti, dostupan kao plutajući widget i zasebna stranica.
+ AI i strojno učenje

## Aplikacija uključuje tri pristupa analizi:

+ Analiza po DBSCAN zonama
+ Prostorno bliske detekcije grupiraju se u žarišta. Za svaku zonu, datum i četverosatni blok izrađuje se skup podataka s povijesnim značajkama detekcija i letova. Uspoređuju se Random Forest, Gradient Boosting, logistička regresija, k-NN i višeslojni perceptron (MLP), uz vremenski uređenu podjelu podataka. Model za predikciju bira se prema F1-mjeri, uz ROC-AUC kao dodatni kriterij.
+ Prostorna mreža rizika
+ Random Forest razlikuje zabilježene detekcije od nasumično generiranih pozadinskih primjera. Rezultat je relativni indeks rizika po ćelijama graničnog koridora, koji se uspoređuje s pokrivenošću GPX točkama.
+ Heuristička procjena lokacije
+ Brza procjena temeljena na obližnjim povijesnim detekcijama i vremenskim obrascima, bez treniranja modela.

AI asistent koristi Anthropic Claude API i pozivanje alata (tool-use) za dohvat rezultata analiza i statistike iz aplikacije.

## Tehnologije i arhitektura:
Dio sustava	Tehnologije
Web aplikacija	Laravel 11, PHP 8.2+, MySQL
Korisničko sučelje	AdminLTE 3, Leaflet.js, Chart.js
Uloge i ovlasti	Spatie Laravel Permission
Obrada putanja	Vlastiti GPX parser
ML servis	Python, FastAPI, pandas, NumPy, scikit-learn
Pristup bazi iz ML servisa	PyMySQL
AI asistent	Anthropic Claude API, integriran kroz Laravel

Laravel aplikacija i Python ML servis pokreću se kao zasebni procesi. Aplikacija poziva ML servis putem REST API-ja, a oba dijela pristupaju istoj MySQL bazi. Takva arhitektura omogućuje zaseban razvoj i nadogradnju ML komponente.

## Podaci i ograničenja

Projekt koristi sintetičke podatke za razvoj, testiranje i demonstraciju. AI asistent dohvaća podatke pohranjene u bazi; oni ne predstavljaju stvarne operativne događaje.
Predikcije su eksperimentalne i služe kao podrška odlučivanju. Relativni indeks rizika nije potvrđena vjerojatnost događaja, a izostanak zabilježene detekcije ne dokazuje odsustvo aktivnosti.
Povijesne značajke u analizi po zonama pomaknute su unatrag, ali DBSCAN zone trenutačno se određuju iz cijelog dostupnog skupa. Za strogu evaluaciju budućih predikcija potrebno je i formiranje zona ograničiti na podatke dostupne prije testnog razdoblja. Procjena trajanja nadzora po zonama također koristi pojednostavljenu dodjelu cijelog leta zoni kroz koju prolazi.

Prva faza projekta obuhvaća strukturirane podatke o detekcijama i GPX zapisima letova, a druga faza projekta će biti implementacija YOLO algoritma za obradu video zapisa sa letjelica sa mogućnošću porepoznavanja osoba/vozila, kao i automatsko spremanje koordinata detekcije. Na temelju nedograđenom projekta ću raditi i diplomski rad.
