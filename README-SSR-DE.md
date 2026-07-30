# Feedback Product Offer GEO F&M – Version 5.0.16

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
