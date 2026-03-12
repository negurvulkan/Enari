---
translation_key: weltbau.sprachen.veyrathi.schriftsystem
---

# Veyrathi - writing system

## Working status

This draft describes a first canonical **Veyrathi block font**. It is intended as a **featural alphabet script with syllable blocks**: The individual characters represent phonemes, but are put together to form compact syllable fields, as in Hangeul.

## 1. Foundation

- Phonological basis: **26 core phonemes**
- Of which **5 vowels** and **21 consonants**
- **27 basic characters** are recommended for the font:
  - 21 consonant characters
  - 5 vowel characters
  - 1 silent carrier for vowel-sounding syllables
- Optionally, **1 hiatus sign** is added if the glottal stop or a hard vowel separation should be visibly written

This means that the font in the narrower sense needs **27 independent letters**, and in the broader sense **28 basic graphic characters**.

## 2. Principles of form

All characters are built from a few basic elements:

- **period**
- **vertical bar**
- **Horizontal bar**
- **left semicircle**
- **right semicircle**
- **upper semicircle**
- **lower semicircle**

Design principle:

- Consonants primarily carry **place** and **type** of articulation
- Vowels are simpler and more compact
- An additional **dot** preferably marks the voiced or softer partner form for consonants
- Glides remain visually easier; Africates may be noticeably more complex
- Each letter should contain a maximum of **2 to 3 basic elements**

## 3. Consonant inventory of writing

### 3.1 Labial family

Basic idea: **vertical line + left semicircle**

| phoneme | Design | Function |
|---|---|---|
| `p` | Vertical line + left semicircle | voiceless labial closure |
| `b` | `p` + dot | voiced labial closure |
| `m` | `p` + lower horizontal bar | labial nasal |
| `f` | `p` + upper horizontal bar | voiceless labial fricative sound |
| `v` | `f` + dot | voiced labial fricative sound |

### 3.2 Alveolar family

Basic idea: **vertical line** as a trunk

| phoneme | Design | Function |
|---|---|---|
| `t` | Vertical bar + top horizontal bar | voiceless alveolar closure |
| `d` | `t` + dot | voiced alveolar closure |
| `n` | Vertical bar + bottom horizontal bar | alveolar nasal |
| `s` | Vertical line + right semicircle | voiceless alveolar fricative sound /s/ |
| `z` | `s` + dot | voiced alveolar fricative sound /z/ |

### 3.3 Dental family

Basic idea: **long trunk + underhook**

| phoneme | Design | Function |
|---|---|---|
| `th` | long vertical line + short underhook | voiceless dental fricative sound /θ/ |

Note:

- `th` is a separate dental slot and not a ligature from `t + h`.

### 3.4 Velar family

Basic idea: **vertical line + right semicircle**

| phoneme | Design | Function |
|---|---|---|
| `k` | Vertical line + right semicircle | voiceless velar closure |
| `g` | `k` + dot | voiced velar closure |

### 3.5 Postalveolar fricative sounds

Basic idea: **upper semicircle + trunk**

| phoneme | Design | Function |
|---|---|---|
| `sh` | upper semicircle + vertical line | voiceless postalveolar fricative sound /ʃ/ |
| `zh` | `sh` + dot | voiced postalveolar fricative sound /ʒ/ |

Note:

- `sh` and `zh` are intentionally sibling characters; the same base form is made voiced via the dot.

### 3.6 Affricate family

Basic idea: **postalveolar base + underhook**

| phoneme | Design | Function |
|---|---|---|
| `j` | `zh` + short underhook | voiced postalveolar affricates /d͡ʒ/ |

### 3.7 Sonorants and gliding sounds| phoneme | Design | Function |
|---|---|---|
| `h` | single vertical line | glottal breath sound |
| `l` | Vertical line + left semicircle below small | lateral sound |
| `r` | Vertical bar + short middle bar to the right | red sound |
| `w` | lower semicircle + short vertical line | labiovelar gliding sound |
| `y` | slim upper semicircle + short vertical line | phonemic /j/; word initial and after vowels as `[j]`, after consonants often palatalizing as in `yava`, `zeya`, `veyra`, `zyran` |

Note:

- The current vocabulary distinguishes `y = /j/` and `j = /d͡ʒ/` synchronously. The block font therefore reserves two separate characters with varying levels of visual complexity.
- The `y` character is preserved even where the standard language phonetically only hears palatalization (`zy` -> `[ʑ]`, `ky` -> `[kʲ]`); the font continues to map to the phonemic slot `/j/`.

## 4. Vowel inventory of writing

The vowels are deliberately kept simpler than the consonants. They only carry quality, not length.

| phoneme | Design | Placement in the block |
|---|---|---|
| `a` | short vertical line | right side |
| `e` | short vertical line + dot | right side |
| `i` | two short vertical lines | right side |
| `o` | short horizontal line | Bottom |
| `u` | short horizontal line + dot | Bottom |

## 5. Silent carrier and hiatus sign

| characters | Design | Function |
|---|---|---|
| Zero carrier | short vertical line with a dot below | fills the initial position before vowel syllables |
| Hiatus sign | single dot between two vowel signs or above the second block | marks conscious separation or audible glottal stop |

Rule:

- Vowel-sounding syllables are **not written without an initial slot**.
- Instead there is a **silent carrier** at the beginning.
- The glottal stop remains **secondary** and does not have to be written down in every word.

## 6. Block construction of the syllables

### 6.1 Basic principle

Each syllable forms an approximately square block.

Maximum structure:

- **Initial** left or top left
- **Vowel** right or bottom
- **Final sound** below as a reduced final form

The `(C)V(C)` phonology fits directly into this system.

### 6.2 Block types

| Syllable type | Construction |
|---|---|
| `V` | zero carrier + vowel |
| `CV` | Consonant + vowel |
| `VC` | Zero carrier + vowel + minor final consonant |
| `CVC` | Consonant + vowel + minor final consonant |

### 6.3 Placement of vowels

- **Right vowels**: `a`, `e`, `i`
- **Subvowels**: `o`, `u`

This results in a visually clear division:

- light/near-front vowels tend to be **vertical**
- round/lower vowels tend to be **horizontal**

### 6.4 Final forms

The final sound uses the same basic symbol as the initial sound, but:

- reduced in size
- placed in the lower block zone
- without changing the basic components

The dot is retained so that voiced and voiceless final consonants remain distinguishable in the written image, even if this opposition should later be reduced phonologically.

## 7. Writing rules

### 7.1 Basic phonemic rule

The writing is essentially **phonemic**:

- a phoneme = a letter
- `sh`, `zh` and `th` each receive **its own character**
- `y` and `j` also get **separated characters**
- Latin digraphs are only transcription, not an analysis of block writing

### 7.2 Morphological transparency

- Prefixes remain **syllabically visible** in the typeface
- Compounds can be written normally one after the other
- Endings like `-ath`, `-or`, `-i` are not merged, but written in blocks

### 7.3 Word separation

- Words are separated by spaces
- There are no hyphens within a word in the standard font
- The Latin apostrophe remains relevant only for transliteration or special cases

## 8. Transcription

Recommended 1:1 transcription:| Block letter phoneme | Latin transliteration |
|---|---|
| `sh` | `sh` |
| `zh` | `zh` |
| `th` | `th` |
| `y` | `y` |
| `j` | `j` |
| `w` | `w` |

All other characters are reproduced directly 1:1 according to the well-known Latin standard.

## 9. Design effect

The writing should:

- appear **clear and orderly**
- form **rhythmic block groups** from a distance
- look **soft** despite the geometric simplicity
- retain an organic, not too technical feel through arches and dots

The contrast of:

- stable vertical and horizontal axes
- soft half-arches
- small dots as fine distinguishing features

## 10. Recommendation for the next draft

The next step should be worked out:

1. a concrete glyph set with sketches per character
2. precise proportions for initial, vowel and final sound zones
3. a list of example words in block segmentation
4. a decision as to whether the hiatus point should be optional or orthographically mandatory
5. a final refinement of the separate `y`/`j` series
