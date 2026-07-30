# Kunden-Feedback 5.0.8 – PlentyONE-Compiler-Fix

Diese Version behebt die beim Bereitstellen gemeldeten Compilerfehler in `FeedbackService.php` und `ProductSchemaBuilder.php`:

- keine direkte Verwendung von `new` im Plugin-Code,
- keine Verwendung von `is_scalar()`,
- keine dynamischen Objekteigenschaften wie `$object->{$name}`,
- `ProductSchemaBuilder` wird per Dependency Injection in `FeedbackService` eingebunden,
- der Schema-Builder verarbeitet das normalisierte Artikeldokument ausschließlich als Array.


## Version 5.0.7 – ShopBuilder-Preview-Fix

- SSR- und Product-Schema-Abfragen werden im ShopBuilder-Editor nicht ausgeführt.
- Das Widget zeigt im Editor eine statische, zuverlässige Vorschau.
- Fehlende `item.documents[0].data`-Werte werden im Frontend mit sicheren Standardwerten abgefangen.
- Alte ShopBuilder-Inhalte ohne die neuen Einstellungen bleiben kompatibel.
- Verhindert, dass ein Twig-Fehler die Widget-Vorschau leert oder eine Artikeldetailansicht auf einen Fallback-Inhalt zurückfällt.

# Kunden-Feedback 5.0.6 – SSR-, Product- und Offer-Erweiterung

Diese Variante basiert auf dem offiziellen PlentyONE-Plugin **Kunden-Feedback 5.0.4** und erweitert die bisherige SSR-Version 5.0.5.

## Ergänzungen

- Die erste Bewertungsseite wird direkt im initialen HTML der Artikelseite ausgegeben.
- Die Anzahl richtet sich nach der Widget-Einstellung **„Anzahl der Bewertungen pro Seite“** und ist serverseitig auf maximal 10 begrenzt.
- Das Plugin erzeugt serverseitig ein vollständiges `Product`-Objekt mit verschachteltem `Offer`.
- Preis, Währung, SKU, Produktname, Bilder, Hersteller, GTIN und Verfügbarkeit werden – soweit vorhanden – aus der aktuell serverseitig geladenen PlentyONE-Variante übernommen.
- Die öffentliche Produkt-URL wird ohne ShopBuilder-Vorschauparameter als `url` und Basis der `@id` verwendet.
- Bei vorhandenen Bewertungen werden `AggregateRating` und die anfänglich sichtbaren `Review`-Objekte in dasselbe `Product`-Schema integriert.
- Artikel ohne Bewertungen erhalten weiterhin `Product` und `Offer`, aber keine leeren oder erfundenen Rating-Felder.
- Das vorhandene clientseitige Bewertungsschema wird unterdrückt, sobald das serverseitige Schema vorhanden ist.
- AJAX, Bewertungsformular, Moderation, Kommentare, Filter und „Mehr laden“ bleiben bestehen.

## Neue Widget-Einstellungen

Die Einstellungen befinden sich im ShopBuilder direkt im Widget **„Artikelbewertung“**:

- **Bewertungen serverseitig ausgeben**  
  Aktiviert die initiale HTML-Ausgabe und das serverseitige Schema.
- **Product- und Offer-Schema serverseitig ausgeben**  
  Sollte aktiviert bleiben, wenn dieses Plugin das vollständige Produktschema liefern soll.
- **Verkäufername im Offer-Schema**  
  Standardwert: `Four & More GmbH`.
- **Anzahl der Bewertungen pro Seite**  
  Empfohlen: 5 bis 10.

## Installation

Diese ZIP ist ein **Drop-in-Fork mit demselben Plugin-Namen und Namespace `Feedback`**. Das originale Marketplace-Plugin darf deshalb nicht gleichzeitig im selben Plugin-Set installiert sein.

1. Plugin-Set kopieren und als Test-Set verwenden.
2. Originales Plugin **„Kunden-Feedback“** im Test-Set entfernen bzw. deaktivieren.
3. Diesen Fork als Git-/Custom-Plugin mit dem Ordnernamen `Feedback` hinzufügen.
4. Plugin-Set bereitstellen.
5. Im ShopBuilder **Inhalte neu generieren**, damit die neuen Widget-Einstellungen geladen werden.
6. Das Widget **„Artikelbewertung“** öffnen und die oben genannten Einstellungen kontrollieren.
7. ShopBuilder-Inhalt speichern und veröffentlichen.

## Kontrolle

Im unverarbeiteten Seitenquelltext sollten bei vorhandenen Rezensionen echte Bewertungstexte vorkommen. Zusätzlich muss dieses Element vorhanden sein:

```html
<script id="feedback-product-jsonld" type="application/ld+json">
```

Das enthaltene JSON-LD sollte mindestens diese Struktur besitzen:

```text
Product
└── offers: Offer
```

Bei vorhandenen freigegebenen Bewertungen zusätzlich:

```text
Product
├── offers: Offer
├── aggregateRating: AggregateRating
└── review: Review[]
```

Bei Artikeln ohne Bewertungen dürfen `aggregateRating` und `review` nicht ausgegeben werden.

## Konflikt mit anderem Product-Schema

Pro Artikelseite sollte möglichst nur ein konsistentes vollständiges `Product`-Objekt ausgegeben werden. Wenn ein anderes Plugin bereits ein vollständiges Product-/Offer-Schema erzeugt, darf nicht parallel ein widersprüchlicher Datensatz mit anderem Preis, anderer SKU oder anderer Verfügbarkeit aktiv sein. In diesem Fall muss die Schema-Strategie im Test-Plugin-Set gezielt geprüft werden.

## Technischer Hinweis

Das Schema beschreibt die beim initialen Serveraufruf ausgewählte Variante. Nach einem rein clientseitigen Variantenwechsel kann sich der sichtbare Preis ändern, ohne dass der ursprüngliche Server-Response neu geladen wird. Variantenspezifische URLs bzw. ein vollständiger Seitenaufruf bleiben daher für die zuverlässigste Auszeichnung sinnvoll.

Die PHP- und JSON-Dateien sowie der Schema-Builder wurden lokal statisch getestet. Ein abschließender Build im konkreten PlentyONE-Test-Plugin-Set ist erforderlich, da die PlentyONE-Repositories und der ShopBuilder-Compiler nur dort verfügbar sind.
