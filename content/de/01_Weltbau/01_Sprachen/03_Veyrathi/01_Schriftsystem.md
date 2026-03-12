---
translation_key: weltbau.sprachen.veyrathi.schriftsystem
---

# Veyrathi - Schriftsystem

## Arbeitsstatus

Dieser Entwurf beschreibt eine erste kanonische **Veyrathi-Blockschrift**. Sie ist als **featurale Alphabetschrift mit Silbenblöcken** gedacht: Die einzelnen Zeichen repräsentieren Phoneme, werden aber wie im Hangeul zu kompakten Silbenfeldern zusammengesetzt.

## 1. Grundlage

- Phonologische Basis: **26 Kernphoneme**
- Davon **5 Vokale** und **21 Konsonanten**
- Für die Schrift werden **27 Grundzeichen** empfohlen:
  - 21 Konsonantenzeichen
  - 5 Vokalzeichen
  - 1 lautloser Träger für vokalisch anlautende Silben
- Optional kommt **1 Hiatuszeichen** dazu, wenn der Glottalstopp oder eine harte Vokaltrennung sichtbar geschrieben werden soll

Damit braucht die Schrift im engeren Sinn **27 eigenständige Buchstaben**, im erweiterten Sinn **28 graphische Grundzeichen**.

## 2. Formprinzipien

Alle Zeichen werden aus wenigen Grundelementen gebaut:

- **Punkt**
- **Vertikalstrich**
- **Horizontalstrich**
- **linker Halbkreis**
- **rechter Halbkreis**
- **oberer Halbkreis**
- **unterer Halbkreis**

Gestaltungsprinzip:

- Konsonanten tragen vor allem **Ort** und **Art** der Artikulation
- Vokale sind einfacher und kompakter
- Ein zusätzlicher **Punkt** markiert bei Konsonanten bevorzugt die stimmhafte oder weichere Partnerform
- Gleitlaute bleiben visuell leichter; Affrikaten dürfen erkennbar komplexer gebaut sein
- Jeder Buchstabe sollte mit höchstens **2 bis 3 Grundelementen** auskommen

## 3. Konsonanteninventar der Schrift

### 3.1 Labialfamilie

Grundidee: **Vertikalstrich + linker Halbkreis**

| Phonem | Bauform | Funktion |
|---|---|---|
| `p` | Vertikalstrich + linker Halbkreis | stimmloser labialer Verschluss |
| `b` | `p` + Punkt | stimmhafter labialer Verschluss |
| `m` | `p` + unterer Horizontalstrich | labialer Nasal |
| `f` | `p` + oberer Horizontalstrich | stimmloser labialer Reibelaut |
| `v` | `f` + Punkt | stimmhafter labialer Reibelaut |

### 3.2 Alveolarfamilie

Grundidee: **Vertikalstrich** als Stamm

| Phonem | Bauform | Funktion |
|---|---|---|
| `t` | Vertikalstrich + oberer Horizontalstrich | stimmloser alveolarer Verschluss |
| `d` | `t` + Punkt | stimmhafter alveolarer Verschluss |
| `n` | Vertikalstrich + unterer Horizontalstrich | alveolarer Nasal |
| `s` | Vertikalstrich + rechter Halbkreis | stimmloser alveolarer Reibelaut /s/ |
| `z` | `s` + Punkt | stimmhafter alveolarer Reibelaut /z/ |

### 3.3 Dentalfamilie

Grundidee: **langer Stamm + Unterhaken**

| Phonem | Bauform | Funktion |
|---|---|---|
| `th` | langer Vertikalstrich + kurzer Unterhaken | stimmloser dentaler Reibelaut /θ/ |

Hinweis:

- `th` ist ein eigener dentaler Slot und keine Ligatur aus `t + h`.

### 3.4 Velarfamilie

Grundidee: **Vertikalstrich + rechter Halbkreis**

| Phonem | Bauform | Funktion |
|---|---|---|
| `k` | Vertikalstrich + rechter Halbkreis | stimmloser velarer Verschluss |
| `g` | `k` + Punkt | stimmhafter velarer Verschluss |

### 3.5 Postalveolare Reibelaute

Grundidee: **oberer Halbkreis + Stamm**

| Phonem | Bauform | Funktion |
|---|---|---|
| `sh` | oberer Halbkreis + Vertikalstrich | stimmloser postalveolarer Reibelaut /ʃ/ |
| `zh` | `sh` + Punkt | stimmhafter postalveolarer Reibelaut /ʒ/ |

Hinweis:

- `sh` und `zh` sind absichtlich Geschwisterzeichen; dieselbe Basisform wird über den Punkt stimmhaft gemacht.

### 3.6 Affrikatenfamilie

Grundidee: **postalveolare Basis + Unterhaken**

| Phonem | Bauform | Funktion |
|---|---|---|
| `j` | `zh` + kurzer Unterhaken | stimmhafte postalveolare Affrikate /d͡ʒ/ |

### 3.7 Sonoranten und Gleitlaute

| Phonem | Bauform | Funktion |
|---|---|---|
| `h` | einzelner Vertikalstrich | glottaler Hauchlaut |
| `l` | Vertikalstrich + linker Halbkreis unten klein | lateraler Laut |
| `r` | Vertikalstrich + kurzer Mittelstrich nach rechts | rotischer Laut |
| `w` | unterer Halbkreis + kurzer Vertikalstrich | labiovelarer Gleitlaut |
| `y` | schlanker oberer Halbkreis + kurzer Vertikalstrich | phonemisches /j/; wortinitial und nach Vokalen als `[j]`, nach Konsonanten oft palatalisierend wie in `yava`, `zeya`, `veyra`, `zyran` |

Hinweis:

- Der aktuelle Wortschatz unterscheidet `y = /j/` und `j = /d͡ʒ/` synchron. Die Blockschrift reserviert deshalb zwei getrennte Zeichen mit unterschiedlich schwerer visueller Komplexität.
- Das `y`-Zeichen bleibt auch dort erhalten, wo die Standardsprache phonetisch nur Palatalisierung hört (`zy` -> `[ʑ]`, `ky` -> `[kʲ]`); die Schrift bildet weiterhin den phonemischen Slot `/j/` ab.

## 4. Vokalinventar der Schrift

Die Vokale bleiben bewusst einfacher als die Konsonanten. Sie tragen nur Qualität, keine Länge.

| Phonem | Bauform | Platzierung im Block |
|---|---|---|
| `a` | kurzer Vertikalstrich | rechte Seite |
| `e` | kurzer Vertikalstrich + Punkt | rechte Seite |
| `i` | zwei kurze Vertikalstriche | rechte Seite |
| `o` | kurzer Horizontalstrich | Unterseite |
| `u` | kurzer Horizontalstrich + Punkt | Unterseite |

## 5. Lautloser Träger und Hiatuszeichen

| Zeichen | Bauform | Funktion |
|---|---|---|
| Nullträger | kurzer Vertikalstrich mit Punkt darunter | füllt die Anlautposition vor vokalischen Silben |
| Hiatuszeichen | einzelner Punkt zwischen zwei Vokalzeichen oder über dem zweiten Block | markiert bewusste Trennung oder hörbaren Glottalstopp |

Regel:

- Vokalisch anlautende Silben werden **nicht ohne Anlautslot** geschrieben.
- Stattdessen steht im Anlaut ein **lautloser Träger**.
- Der Glottalstopp bleibt **sekundär** und muss nicht in jedem Wort mitgeschrieben werden.

## 6. Blockbau der Silben

### 6.1 Grundprinzip

Jede Silbe bildet einen annähernd quadratischen Block.

Maximalstruktur:

- **Anlaut** links oder links-oben
- **Vokal** rechts oder unten
- **Auslaut** unten als verkleinerte Endform

Die Phonologie `(C)V(C)` passt direkt zu diesem System.

### 6.2 Blocktypen

| Silbentyp | Aufbau |
|---|---|
| `V` | Nullträger + Vokal |
| `CV` | Konsonant + Vokal |
| `VC` | Nullträger + Vokal + kleiner Endkonsonant |
| `CVC` | Konsonant + Vokal + kleiner Endkonsonant |

### 6.3 Platzierung der Vokale

- **Rechtsvokale**: `a`, `e`, `i`
- **Untervokale**: `o`, `u`

Das ergibt eine visuell klare Zweiteilung:

- helle/frontnahe Vokale eher **vertikal**
- runde/tiefere Vokale eher **horizontal**

### 6.4 Auslautformen

Der Auslaut nutzt dasselbe Grundzeichen wie der Anlaut, aber:

- verkleinert
- in die untere Blockzone gesetzt
- ohne Umstellung der Grundbestandteile

Der Punkt bleibt erhalten, damit stimmhafte und stimmlose Endkonsonanten im Schriftbild unterscheidbar bleiben, auch wenn diese Opposition später phonologisch noch reduziert werden sollte.

## 7. Schreibregeln

### 7.1 Phonemische Grundregel

Die Schrift ist im Kern **phonemisch**:

- ein Phonem = ein Buchstabe
- `sh`, `zh` und `th` erhalten je **ein eigenes Zeichen**
- `y` und `j` erhalten ebenfalls **getrennte Zeichen**
- lateinische Digraphen sind nur Umschrift, keine Analyse der Blockschrift

### 7.2 Morphologische Transparenz

- Präfixe bleiben im Schriftbild **silbisch sichtbar**
- Komposita können normal hintereinander geschrieben werden
- Endungen wie `-ath`, `-or`, `-i` werden nicht verschmolzen, sondern blockweise geschrieben

### 7.3 Worttrennung

- Wörter werden durch Leerzeichen getrennt
- Innerhalb eines Wortes gibt es keine Bindestriche in der Standardschrift
- Das lateinische Apostroph bleibt nur für Umschrift oder Spezialfälle relevant

## 8. Transkription

Empfohlene 1:1-Umschrift:

| Blockschrift-Phonem | Lateinische Umschrift |
|---|---|
| `sh` | `sh` |
| `zh` | `zh` |
| `th` | `th` |
| `y` | `y` |
| `j` | `j` |
| `w` | `w` |

Alle anderen Zeichen werden direkt 1:1 nach dem bekannten lateinischen Standard wiedergegeben.

## 9. Gestalterische Wirkung

Die Schrift soll:

- **klar und geordnet** wirken
- aus etwas Entfernung **rhythmische Blockgruppen** bilden
- trotz der geometrischen Einfachheit **weich** aussehen
- durch Bögen und Punkte eine organische, nicht zu technische Anmutung behalten

Besonders passend für Veyrathi ist der Kontrast aus:

- stabilen Vertikal- und Horizontalachsen
- weichen Halbbögen
- kleinen Punkten als feinen Unterscheidungsmerkmalen

## 10. Empfehlung für die nächste Ausarbeitung

Als nächster Schritt sollten ausgearbeitet werden:

1. ein konkretes Glyphenset mit Skizzen pro Zeichen
2. genaue Proportionen für Anlaut-, Vokal- und Auslautzone
3. eine Liste mit Beispielwörtern in Blocksegmentierung
4. eine Entscheidung, ob der Hiatuspunkt nur optional oder orthographisch verpflichtend sein soll
5. eine endgültige Feinausarbeitung der getrennten `y`-/`j`-Reihe
