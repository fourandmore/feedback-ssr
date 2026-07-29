# Kunden-Feedback 5.0.5 – SSR-Erweiterung

Diese Variante basiert auf dem offiziellen PlentyONE-Plugin **Kunden-Feedback 5.0.4**.

## Ergänzungen

- Die erste Bewertungsseite wird direkt im initialen HTML der Artikelseite ausgegeben.
- Die Anzahl richtet sich nach der Widget-Einstellung „Anzahl der Bewertungen pro Seite“ und ist serverseitig auf maximal 10 begrenzt.
- `AggregateRating` und die serverseitig sichtbaren `Review`-Einträge werden als JSON-LD ausgegeben.
- Das vorhandene clientseitige Schema wird unterdrückt, sobald das serverseitige Schema vorhanden ist.
- AJAX, Bewertungsformular, Moderation, Kommentare und „Mehr laden“ bleiben unverändert.

## Installation

Diese ZIP ist ein **Drop-in-Fork mit demselben Plugin-Namen und Namespace `Feedback`**. Das originale Marketplace-Plugin darf deshalb nicht gleichzeitig im selben Plugin-Set installiert sein.

1. Plugin-Set kopieren und als Test-Set verwenden.
2. Originales Plugin „Kunden-Feedback“ im Test-Set entfernen/deaktivieren.
3. Diesen Fork als Git-/Custom-Plugin mit dem Ordnernamen `Feedback` hinzufügen.
4. Plugin-Set bereitstellen.
5. Im ShopBuilder das Widget „Artikelbewertung“ öffnen.
6. „Bewertungen serverseitig ausgeben“ aktivieren.
7. „Anzahl der Bewertungen pro Seite“ auf 5 bis 10 setzen.

## Kontrolle

Im unverarbeiteten Seitenquelltext müssen echte Bewertungstexte vorkommen. Zusätzlich muss ein Element mit der ID `feedback-product-jsonld` vorhanden sein.

## Hinweis

Die Änderung wurde auf Basis des Quellcodes statisch geprüft. Ein abschließender Lauf im konkreten PlentyONE-Test-Plugin-Set ist notwendig, da die PlentyONE-Repositories und der ShopBuilder-Compiler nur dort verfügbar sind.
