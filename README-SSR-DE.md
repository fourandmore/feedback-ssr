# Feedback Product Offer GEO F&M 5.0.15

Diese Version erweitert das offizielle Kunden-Feedback-Plugin für Four & More um:

- serverseitig sichtbare Produktbewertungen
- serverseitiges Product-/Offer-Schema
- AggregateRating und einzelne Review-Objekte
- verständlichen Leerzustand bei Artikeln ohne Bewertungen
- serverseitiges FAQPage-Schema aus der Artikel-/Varianteneigenschaft **ID 151**
- optionales separates ShopBuilder-Widget **„FAQ serverseitig + Schema“**

## FAQ-Schema aus Eigenschaft 151

Im ShopBuilder-Hauptwidget **„Feedback Product Offer GEO F&M“** stehen zwei neue Einstellungen zur Verfügung:

- **FAQPage aus Artikeleigenschaft serverseitig ausgeben**
- **Eigenschafts-ID für FAQ-Schema** (Standard: `151`)

Ist die Funktion aktiviert, liest das Plugin serverseitig den HTML-Inhalt der Eigenschaft 151 aus dem aktuellen Artikel-/Variantendokument. Unterstützt wird die vorhandene FAQ-Struktur mit:

```html
<section class="faq-section">
  <details class="faq-item">
    <summary>Frage</summary>
    <div class="faq-answer"><p>Antwort</p></div>
  </details>
</section>
```

Aus den sichtbaren Fragen und Antworten wird direkt im initialen HTML erzeugt:

```html
<script id="feedback-faq-property-jsonld-151" type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "FAQPage",
  "mainEntity": []
}
</script>
```

Die Eigenschaft selbst bleibt für die sichtbare FAQ-Ausgabe zuständig. Das Plugin erzeugt daraus nur das serverseitige Schema und gibt keinen zweiten sichtbaren FAQ-Bereich aus.

## Einstellungen

Empfohlen im Hauptwidget:

- **Bewertungen serverseitig ausgeben:** aktiviert
- **Product- und Offer-Schema serverseitig ausgeben:** aktiviert
- **FAQPage aus Artikeleigenschaft serverseitig ausgeben:** aktiviert
- **Eigenschafts-ID für FAQ-Schema:** `151`

## Migration vom alten FAQ-Code-Widget

Wenn das FAQ-Schema bisher durch ein JavaScript-Code-Widget mit `automatic-faq-schema` erzeugt wurde, sollte dieses alte Code-Widget entfernt werden. Andernfalls können zwei FAQPage-Datensätze entstehen.

## Prüfung

Nach der Bereitstellung im Seitenquelltext suchen nach:

```text
feedback-faq-property-jsonld-151
```

Außerdem sollten `feedback-product-jsonld` und die sichtbaren Bewertungstexte weiterhin im ursprünglichen Seitenquelltext vorhanden sein.

## Technischer Name

Der interne Plugin-Name und Namespace bleiben aus Kompatibilitätsgründen `Feedback`. Sichtbar heißt das Plugin und das Hauptwidget **„Feedback Product Offer GEO F&M“**.
