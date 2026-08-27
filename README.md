# Feedback Product Offer GEO F&M

Entwickelt und gepflegt von **Four & More GmbH** für plentyShop LTS.

Das Plugin basiert auf dem ursprünglichen PlentyONE-Feedback-Plugin und erweitert es um:

- serverseitig gerenderte Kundenrezensionen,
- echtes serverseitiges `Product`-/`ProductGroup`-/`Offer`-JSON-LD,
- `AggregateRating` und einzelne `Review`-Objekte,
- serverseitiges `FAQPage`-Schema aus Artikeleigenschaften,
- je Plugin-Set konfigurierbare FAQ-Property (Standard `151`),
- Bewertungssterne in Kategorieansichten über `Ceres::CategoryItem.BeforePrices`,
- strukturierte Artikelzustände und Kategorien,
- artikelspezifische Versanddaten und eine konfigurierbare Rückgaberichtlinie,
- optionales `VideoObject` bei vollständig gepflegten Videodaten,
- bereinigte anonyme Autorenbezeichnungen,
- verständliche Leerzustände bei Artikeln ohne Bewertungen.

## Version 5.0.41

Version 5.0.41 baut direkt auf dem in Ceres 5.0.81 geprüften Stand 5.0.40 auf. Der funktionierende `page-metadata`-Override, die Kategorie-Bewertungssterne und die serverseitige FAQ-Auflösung bleiben unverändert.

- PlentyONE-Standardversandkosten bleiben die erste und verbindliche Quelle. Optional können je Plugin-Set Paket- und Speditionspreise als Fallback gepflegt werden; die Auswahl erfolgt über konfigurierte Speditionsprofile oder das Bruttogewicht.
- Die Video-Einstellungen akzeptieren mehrere kommagetrennte Property-IDs. Ein `VideoObject` entsteht weiterhin nur mit einer validen YouTube-ID und einem validen Upload-Datum.
- `ProductGroup.hasVariant` wird ergänzt, wenn PlentyONE echte Geschwistervarianten im Layout-Container mitliefert. Fehlen diese Dokumente, wird kein Varianten-Markup erfunden.
- ProvenExpert-JSON-LD bleibt bewusst unangetastet.

Die Versand-Fallbacks sind standardmäßig deaktiviert. Vor der Aktivierung müssen Paketpreis, Speditionspreis, Speditionsprofile, Gewichtsschwelle, Länder und Laufzeiten je Shop fachlich geprüft werden.

## Voraussetzungen

- plentyShop LTS / Ceres
- IO
- ein ShopBuilder-Inhalt für die Artikeldetailseite

## Pflege und Support

**Four & More GmbH**  
E-Mail: info@four-more.de  
Telefon: +49 7260 849577

## Herkunft und Lizenz

Dieses Projekt ist eine modifizierte Fassung des ursprünglichen PlentyONE-Feedback-Plugins. Die ursprünglichen Urheber- und Markenhinweise sowie die Bedingungen der GNU Affero General Public License Version 3 bleiben erhalten. Änderungen und Erweiterungen ab Version 5.0.5 werden von Four & More GmbH gepflegt. Siehe [LICENSE.md](LICENSE.md).
