# Feedback Product Offer GEO F&M – Version 5.0.31
## Technischer Plugin-Name / Namespace

Ab Version 5.0.21 verwendet diese Fork den eindeutigen technischen Plugin-Namen und PHP-Namespace `FeedbackGeoFM`. Dadurch kollidiert sie nicht mehr mit einem Plugin, das weiterhin den technischen Namen/Namespace `Feedback` verwendet. Sichtbare Bezeichnungen im ShopBuilder bleiben unverändert.


**Entwickelt und gepflegt von Four & More GmbH.**

## Version 5.0.31 – transportfeste FAQ-Unicode-Ausgabe

- Das serverseitige FAQPage-Schema serialisiert Umlaute und andere Nicht-ASCII-Zeichen als JSON-Unicode-Escapes, beispielsweise `Für` als `F\u00fcr`.
- Nach `JSON.parse()` steht weiterhin der korrekte Originaltext `Für` zur Verfügung.
- Die ASCII-sichere Transportform verhindert die in der Billiard-Royal-Preview beobachtete nachträgliche Umwandlung zu `F&Atilde;&frac14;r`.
- Die Textbereinigung aus Version 5.0.30 und die Hauptvariantenauflösung bleiben erhalten.
- Product-, ProductGroup- und Offer-Schema bleiben unverändert.

## Version 5.0.30 – bereinigte FAQ-Zeichenkodierung

- Mehrfach als HTML-Entities kodierte Inhalte aus Eigenschaft 151 werden vor dem Aufbau des FAQPage-Schemas kontrolliert vollständig dekodiert.
- Verbliebene typische UTF-8-/Latin-1-Fehlkodierungen werden ausschließlich für bekannte Zeichenfolgen repariert, damit korrektes UTF-8 unverändert bleibt.
- Umlaute, `ß`, Grad- und Hochzeichen sowie häufige typografische Zeichen erscheinen dadurch als echte Unicode-Zeichen im JSON-LD.
- Die Product-, ProductGroup- und Offer-Ausgabe bleibt unverändert.

## Version 5.0.29 – Hauptvarianten-Artikeldokument über SingleItem

- Eigenschaft 151 wird zuerst aus dem vollständigen plentyShop-Artikeldokument der ausdrücklich angeforderten Hauptvariante gelesen.
- Der Abruf nutzt denselben `ItemSearchService`-/`SingleItem`-Pfad wie der offizielle IO-`ItemService`; die veraltete `ItemDataLayer`-Schnittstelle wird nicht verwendet.
- Das aktuelle Kindvarianten-Dokument bleibt ausgeschlossen. Die aufgelöste Hauptvarianten-ID wird direkt an die Artikelsuche übergeben.
- Die bisherigen Variation-Property- und Hauptartikel-Repositories bleiben als nachgelagerte Fallbacks erhalten.
- Bei erfolgreichem Abruf lautet der Diagnosepfad `source="main-variation-item-document"`.
- Product-, ProductGroup- und Offer-Schema bleiben unverändert.

## Version 5.0.28 – zusätzlicher Hauptartikel-Property-Fallback

- Die Hauptvarianten-ID wird weiterhin zwingend ermittelt; Kindvariantenwerte und das aktuelle Kindvarianten-Dokument bleiben ausgeschlossen.
- Liefert das Variation-Property-Repository keinen Wert für Eigenschaft 151, lädt das Plugin den zugehörigen PlentyONE-Artikel serverseitig mit seinen `properties`.
- Damit werden auch ältere Item-Properties bzw. Merkmale erfasst, die im Webshop-Artikeldokument vorhanden sind, aber nicht vom Variation-Property-Repository zurückgegeben werden.
- Der Diagnosemarker verwendet für diesen Pfad `source="main-item-repository"` und behält die ermittelte Hauptvarianten-ID bei.
- Product-, ProductGroup- und Offer-Schema bleiben unverändert.

## Version 5.0.27 – FAQ immer von der Hauptvariante

- Das serverseitige FAQPage-Schema liest Eigenschaft 151 ausschließlich von der Hauptvariante des Artikels.
- Eine deaktivierte Property-Vererbung auf Kindvarianten verhindert die FAQ-Ausgabe dadurch nicht mehr.
- Weder ein abweichender Wert an der Kindvariante noch Property 151 aus deren ShopBuilder-Artikeldokument wird für das Schema verwendet.
- Fehlt `mainVariationId` im reduzierten Layout-Container, ermittelt das Plugin sie serverseitig über die aktuelle Varianten-ID.
- Product-, ProductGroup- und Offer-Schema bleiben unverändert.

## Version 5.0.26 – FAQ-Vererbung auf Kindvarianten

- Kaufbare Kindvarianten erhalten das FAQPage-Schema auch dann, wenn Property 151 von der Hauptvariation geerbt wird.
- Der FAQ-Builder prüft dafür zusätzlich `propertyVariationId`, `mainVariationId` und `parentVariationId`.
- Die vorhandene Product-Logik bleibt unverändert: `ProductGroup` ohne Offer auf der nicht kaufbaren Hauptvariation sowie `Product` mit Offer und `isVariantOf` auf einer kaufbaren Variante.

## Version 5.0.25 – nicht veralteter Einzelartikel-Container

- Die beiden serverseitigen DataProvider für Product/ProductGroup/Offer und FAQPage verwenden jetzt `Ceres::SingleItem.BeforeAddToBasket`.
- `Ceres::SingleItem.BeforePrice` wird nicht mehr als Standardzuordnung verwendet, da dieser Container im aktuellen plentyShop-LTS-Manifest als veraltet gekennzeichnet ist.
- Die Auflösung der Containerargumente unterstützt zusätzlich direkte Artikeldaten, `documents[0].data`, `item.documents[0].data` und objektbasierte Argumente.

## Version 5.0.24 – valides Product/ProductGroup/Offer-Schema

- Product-Markup wird nicht mehr als vom ShopBuilder umgeschriebenes `<script2>`, sondern über den DataProvider **„Product/ProductGroup/Offer JSON-LD serverseitig“** als echtes `<script type="application/ld+json">` im initialen HTML ausgegeben.
- Nicht kaufbare Hauptvarianten mit aktiven Untervarianten werden als `ProductGroup` ohne erfundenes `Offer` ausgegeben.
- Kaufbare konkrete Varianten bleiben `Product` mit eigenem `productID`, variantenspezifischem `Offer`, Preis, Währung, URL und Verfügbarkeit. Die Gruppenzugehörigkeit wird über `isVariantOf` und eine artikelstabile `productGroupID` beschrieben.
- `brand` wird aus dem PlentyONE-Hersteller-/Markenfeld gelesen; `manufacturer` verwendet bevorzugt `responsibleName` und fällt ansonsten auf die globale Herstellerkonfiguration zurück. `seller` bleibt davon getrennt.
- Versanddetails werden nur ausgegeben, wenn die globale Option aktiviert ist und `variation.defaultShippingCosts` für die konkrete Variante vorhanden ist. Die Option ist nach dem Update bewusst standardmäßig deaktiviert, bis Länder und Laufzeiten geprüft wurden.
- Die bisherigen Product-/Offer-Einstellungen wurden aus dem ShopBuilder-Widget in die globale Plugin-Konfiguration **„Strukturierte Produktdaten“** verschoben.

### Einmalige Einrichtung nach dem Update

1. Im Plugin-Set die Standard-Container-Verknüpfungen herstellen oder den DataProvider **„Product/ProductGroup/Offer JSON-LD serverseitig“** mit `Ceres::SingleItem.BeforeAddToBasket` verknüpfen.
2. Unter Plugin-Konfiguration → **Strukturierte Produktdaten** Verkäufer, Hersteller, Rückgabe sowie optional Versand und `variesBy` prüfen.
3. Die konkurrierende Product-JSON-LD-Ausgabe von Ceres bzw. einem anderen Plugin deaktivieren. Pro Produktseite soll nur eine fachlich führende Product/ProductGroup-Struktur vorhanden sein.
4. ShopBuilder-Inhalte neu generieren und im unveränderten Seitenquelltext nach `feedback-product-offer-jsonld` suchen. Der alte Block `feedback-product-jsonld` und `<script2 ... type="application/ld+json">` dürfen nicht mehr vom FeedbackGeoFM-Widget ausgegeben werden.

## Erweiterte strukturierte Produktdaten

Version 5.0.20 ergänzt das bestehende serverseitige Product-/Offer-/Review-/FAQPage-Markup um:

- `Product.category`: bevorzugt aus der Standardkategorie, ansonsten aus dem aktiven Kategoriepfad der kanonischen Produkt-URL,
- `Offer.itemCondition`: Zuordnung der PlentyONE-Artikelzustände zu `NewCondition`, `RefurbishedCondition`, `UsedCondition` oder `DamagedCondition`,
- `Offer.shippingDetails`: artikelspezifische `OfferShippingDetails` aus `variation.defaultShippingCosts`,
- `Organization.hasMerchantReturnPolicy`: konfigurierbare allgemeine Rückgaberichtlinie am Verkäufer des Offers,
- optional `Product.subjectOf` mit einem vollständigen `VideoObject`,
- Bereinigung der Platzhalter-Autoren `Unbekannt`, `Unknown`, `Gast` und vergleichbarer Werte zu `Anonymer Käufer` bzw. `Anonymous buyer`.

Das Video-Markup ist standardmäßig deaktiviert. Es wird nur ausgegeben, wenn Embed-URL, Thumbnail und Upload-Datum vollständig vorhanden sind. Bei YouTube kann das Thumbnail aus der Embed-URL abgeleitet werden.

## Historische ShopBuilder-Einstellungen bis Version 5.0.23

Bis Version 5.0.23 standen diese Einstellungen im Widget **Feedback Product Offer GEO F&M** zur Verfügung. Ab Version 5.0.24 liegen die globalen Product-/Offer-Angaben in der Plugin-Konfiguration:

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

## Version 5.0.22 – echtes serverseitiges FAQPage JSON-LD

Die FAQPage-Ausgabe aus Artikeleigenschaft **151** wurde aus dem ShopBuilder-Widget entfernt und in einen serverseitigen Layout-Container/DataProvider verlagert. Hintergrund: ShopBuilder kann dynamischen Inhalt innerhalb normaler `<script>`-Tags entfernen. Der neue Provider gibt das vollständig gefüllte `<script type="application/ld+json">` außerhalb des ShopBuilder-Parsers direkt im initialen HTML aus.

### Einmalige Container-Verknüpfung

Nach dem Update den DataProvider **„FAQPage JSON-LD serverseitig (Property 151)“** im Plugin-Set mit dem Standardcontainer **`Ceres::SingleItem.BeforeAddToBasket`** verknüpfen (bzw. die Standard-Container-Verknüpfungen des Plugins übernehmen). Das sichtbare FAQ bleibt weiterhin über die Artikeleigenschaft/den bisherigen Inhaltsblock bestehen. Das separate ShopBuilder-Widget **„FAQ Serverseitig und Schema“** ist für Property 151 nicht erforderlich.

Das Hauptwidget **„Feedback Product Offer GEO F&M“** behält den Diagnosemarker `feedback-faq-property-status-151`, erzeugt selbst aber keinen zweiten FAQPage-Scriptblock mehr.


## Version 5.0.23 – Layout-Container-Hotfix

Der FAQ-DataProvider verwendet nun die von PlentyONE dokumentierte Layout-Container-Signatur `call(Twig $twig, $args)`. Die vom Container `Ceres::SingleItem.BeforeAddToBasket` übergebenen Artikeldaten werden aus den unterstützten Argumentformen aufgelöst. Dadurch kann Property 151 serverseitig ausgewertet und als echtes, gefülltes `application/ld+json`-Script ausgegeben werden.

**Wichtig:** `defaultLayoutContainer` in `plugin.json` ist nur eine Standardzuordnung. Im Plugin-Set muss der DataProvider **FAQPage JSON-LD serverseitig (Property 151)** einmal mit **Ceres::SingleItem.BeforeAddToBasket** verknüpft bzw. die Funktion **Standard-Container-Verknüpfungen herstellen** ausgeführt werden. Ohne diese Verknüpfung wird der Provider nicht aufgerufen und im Quelltext erscheint kein FAQ-Script.
