# Feedback Product Offer GEO F&M – Version 5.0.21
## Technischer Plugin-Name / Namespace

Ab Version 5.0.21 verwendet diese Fork den eindeutigen technischen Plugin-Namen und PHP-Namespace `FeedbackGeoFM`. Dadurch kollidiert sie nicht mehr mit einem Plugin, das weiterhin den technischen Namen/Namespace `Feedback` verwendet. Sichtbare Bezeichnungen im ShopBuilder bleiben unverändert.


**Entwickelt und gepflegt von Four & More GmbH.**

## Erweiterte strukturierte Produktdaten

Version 5.0.20 ergänzt das bestehende serverseitige Product-/Offer-/Review-/FAQPage-Markup um:

- `Product.category`: bevorzugt aus der Standardkategorie, ansonsten aus dem aktiven Kategoriepfad der kanonischen Produkt-URL,
- `Offer.itemCondition`: Zuordnung der PlentyONE-Artikelzustände zu `NewCondition`, `RefurbishedCondition`, `UsedCondition` oder `DamagedCondition`,
- `Offer.shippingDetails`: artikelspezifische `OfferShippingDetails` aus `variation.defaultShippingCosts`,
- `Organization.hasMerchantReturnPolicy`: konfigurierbare allgemeine Rückgaberichtlinie am Verkäufer des Offers,
- optional `Product.subjectOf` mit einem vollständigen `VideoObject`,
- Bereinigung der Platzhalter-Autoren `Unbekannt`, `Unknown`, `Gast` und vergleichbarer Werte zu `Anonymer Käufer` bzw. `Anonymous buyer`.

Das Video-Markup ist standardmäßig deaktiviert. Es wird nur ausgegeben, wenn Embed-URL, Thumbnail und Upload-Datum vollständig vorhanden sind. Bei YouTube kann das Thumbnail aus der Embed-URL abgeleitet werden.

## ShopBuilder-Einstellungen

Im Widget **Feedback Product Offer GEO F&M** stehen neue Einstellungen zur Verfügung:

- Versanddaten aktivieren, Lieferländer sowie Bearbeitungs- und Transportzeit,
- Rückgaberichtlinie aktivieren, Länder, Rückgabefrist und öffentliche Richtlinien-URL,
- VideoObject aktivieren sowie Titel, Embed-URL, Thumbnail, Upload-Datum, Beschreibung und Dauer.

Die Versandkosten selbst werden nicht im Widget fest eingetragen. Das Plugin verwendet ausschließlich die für die aktuelle Variante bereitgestellten `variation.defaultShippingCosts`. Fehlt dieser Wert, wird kein `shippingDetails`-Objekt ausgegeben.

**Wichtig:** Werden Paket- und Speditionsartikel über unterschiedliche ShopBuilder-Inhalte ausgespielt, müssen Lieferzeiten und Länder passend je Inhalt eingestellt werden. Das optionale VideoObject darf nur in einem Inhalt aktiviert werden, dessen Videodaten tatsächlich für alle damit ausgespielten Artikel gelten.

## Prüfung nach der Installation

Nach Aktualisierung, Bereitstellung und Neugenerierung der ShopBuilder-Inhalte im Product-JSON-LD prüfen:

```javascript
const product = [...document.querySelectorAll('script[type="application/ld+json"]')]
  .map(el => { try { return JSON.parse(el.textContent); } catch (e) { return null; } })
  .find(data => data?.['@type'] === 'Product');

({
  category: product?.category,
  itemCondition: product?.offers?.itemCondition,
  shippingDetails: product?.offers?.shippingDetails,
  returnPolicy: product?.offers?.seller?.hasMerchantReturnPolicy,
  videoObject: product?.subjectOf
});
```

---

## Korrektur der serverseitigen FAQPage-Ausgabe

Version 5.0.19 gibt die beiden FAQPage-JSON-LD-Varianten direkt als standardkonformes HTML aus:

```html
<script type="application/ld+json">…</script>
```

Geändert wurden ausschließlich:

- das FAQPage-Schema aus der Artikel-/Varianteneigenschaft, standardmäßig Eigenschaft **151**,
- das JSON-LD des separaten manuellen FAQ-ShopBuilder-Widgets.

Das funktionierende Product-/Offer-/Review-Schema wurde **nicht verändert** und verwendet weiterhin seinen bisherigen Ausgabeweg.

Die FAQ-Eigenschaft, der Parser, die Repository-Fallbacks und der Diagnose-Marker aus Version 5.0.18 bleiben unverändert.

## Prüfung nach der Installation

1. Plugin-Set bereitstellen und ShopBuilder-Inhalte neu generieren.
2. Artikeldetailseite öffnen und mit `Strg + U` den unveränderten Seitenquelltext anzeigen.
3. Nach `feedback-faq-property-jsonld-151` suchen.
4. Der Block muss mit einem echten `<script` beginnen und darf nicht mehr als `<script2` ausgegeben werden.

Konsolenprüfung:

```javascript
const faq = document.getElementById('feedback-faq-property-jsonld-151');
({
  vorhanden: Boolean(faq),
  tagName: faq?.tagName,
  type: faq?.getAttribute('type'),
  schema: faq ? JSON.parse(faq.textContent) : null
});
```

Erwartet werden `vorhanden: true`, `tagName: "SCRIPT"` und `type: "application/ld+json"`.

---

## Historie der FAQ-Eigenschaftsunterstützung

## Hotfix für FAQ-Eigenschaft 151

Version 5.0.15 konnte das FAQ-Schema nur erzeugen, wenn die Eigenschaft 151 bereits im ShopBuilder-Artikeldokument unter `properties` enthalten war. Auf manchen Artikeldetailseiten wird die sichtbare Eigenschaft jedoch durch ein separates Widget geladen und fehlt deshalb im Datenobjekt des Feedback-Widgets.

Version 5.0.16 ergänzt einen serverseitigen Repository-Fallback:

- Eigenschaft 151 wird weiterhin zuerst aus `item.documents[0].data.properties` gelesen.
- Fehlt sie dort, wird sie direkt über die aktuelle Varianten-ID geladen.
- Bei vererbten Eigenschaften wird zusätzlich die Hauptvariante geprüft.
- Sprachabhängige Textwerte werden über `valueTexts` oder das PlentyONE-Text-Repository geladen.
- Das FAQ wird nicht doppelt sichtbar ausgegeben; erzeugt wird nur das serverseitige `FAQPage`-JSON-LD.
- Product-, Offer-, Review- und Bewertungsfunktionen bleiben unverändert.

## Nach der Installation

1. Git-Plugin aktualisieren und Plugin-Set neu bereitstellen.
2. ShopBuilder-Inhalte neu generieren.
3. Im Widget „Feedback Product Offer GEO F&M“ prüfen:
   - FAQPage aus Artikeleigenschaft serverseitig ausgeben: aktiviert
   - Eigenschafts-ID: 151
4. Im Seitenquelltext nach `feedback-faq-property-jsonld-151` suchen.
5. In der Browserkonsole prüfen:

```javascript
document.getElementById('feedback-faq-property-jsonld-151')
```

Das Ergebnis muss ein `<script type="application/ld+json">`-Element sein und darf nicht mehr `null` ergeben.


## FAQ-Eigenschaft 151 – Diagnose und Fallbacks

Version 5.0.18 liest Property-Modelle explizit per `toArray()`, durchsucht das komplette Item-Dokument rekursiv, unterstützt zusätzliche Sprachcodes und kann die Varianten-ID aus der Produkt-URL ermitteln. Zusätzlich wird ein unsichtbares Diagnoseelement `feedback-faq-property-status-151` ausgegeben.
