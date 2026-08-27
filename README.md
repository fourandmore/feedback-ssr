Version 5.0.48 now uses the same `VariationAttributeMap` search preset as the current Ceres `/io/variations/map` endpoint for ProductGroup variants, aligning server-rendered GEO data with the storefront variation selector.

Version 5.0.46 completes ProductGroup markup by resolving active, salable child variations server-side through `IO\Services\ItemService`. Each `hasVariant` entry is now a real Product with SKU, price, availability, Offer and shipping details.

Version 5.0.45 resolves the item's real shipping-profile links server-side via PlentyONE's `ItemShippingProfilesRepositoryContract::findByItemId($itemId)`. This removes the dependency on Ceres including the relation in the storefront item document. Exact configured profile prices therefore remain authoritative even when `variation.defaultShippingCosts` contains only a generic parcel value.

Version 5.0.44 korrigiert zusätzlich die Versandprofil-Erkennung in Ceres/PlentyONE 5.0.81: `variation.itemShippingProfiles[].profileId` wird nun ausgewertet. Dadurch greifen profilgenaue Preise auch bei Artikeln, deren PlentyONE-Datensatz die Profile unter diesem offiziellen Pfad liefert.

Version 5.0.43 korrigiert die Versandkosten-Priorität: Ein explizit konfigurierter Preis für das zugewiesene PlentyONE-Versandprofil hat nun Vorrang vor `variation.defaultShippingCosts`. Nur wenn kein Profilpreis passt, wird der PlentyONE-Standardwert verwendet; danach greifen die allgemeinen Paket-/Speditions-Fallbacks.

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

## Version 5.0.42

Version 5.0.42 erweitert den optionalen Versandkosten-Fallback aus 5.0.41 um exakte Preise je PlentyONE-Versandprofil. Format: `6=6,90; 9=59,00; 12=89,00`. PlentyONE-Kosten haben weiterhin Vorrang; ohne Profiltreffer bleibt die bisherige Paket-/Speditionslogik aktiv.

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
