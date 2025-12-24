# Pokloni & Popusti – BOGO / Gifts / Discounts for WooCommerce

## 1. Naziv i kratak opis
Pokloni & Popusti je WooCommerce dodatak koji omogućava korak‑po‑korak kreiranje BOGO (kupite X dobijate Y), gratis i poklon logike zasnovane na proizvodima, kategorijama, brendovima ili linijama. Pravila se primenjuju na korpu i ukupne vrednosti bez menjanja osnovnih WooCommerce funkcija.

## 2. Šta plugin radi (funkcionalnosti)
- Kreira i čuva pravila u posebnoj tabeli (`pnp_rules`) za razne scenarije: kupi X, kupi Y (opciono), uslov na vrednost korpe i dodeli nagradu (poklon/discount item).
- Dodaje poklon proizvode u korpu ili aktivira birač poklona kada su uslovi ispunjeni.
- Sprečava da se poklon kategorija (`PNP_GIFT_CAT`) ručno doda u korpu.
- Resetuje cene pre ponovne primene pravila kako bi zadržao originalne vrednosti i izbegao dvostruke popuste.
- Prati dodeljene poklone po korisniku/porudžbini u tabeli `pnp_user_gifts`.
- Upravlja Ajax pozivima za listanje proizvoda, aktiviranje/deaktiviranje pravila i ažuriranje stanja poklon proizvoda.

## 3. Kako radi u WooCommerce (korpa, totals, sesija)
- Hookovi `woocommerce_before_calculate_totals` (prioriteti 1 i 20) resetuju cene i primenjuju pravila pri svakoj kalkulaciji total-a.
- `woocommerce_cart_loaded_from_session` ponovo primenjuje pravila kada se korpa učita iz sesije.
- `woocommerce_cart_item_removed` i `woocommerce_cart_emptied` odrađuju čišćenje sesije i poklona.
- `woocommerce_add_to_cart_validation` blokira dodavanje proizvoda iz kategorije poklona osim preko pravila.
- Filteri `woocommerce_is_purchasable` i `woocommerce_get_price_html` prilagođavaju dostupnost i prikaz cene za poklon artikle.

## 4. Admin deo
- Glavni meni: **WooCommerce → Pokloni & Popusti** (`toplevel_page_pnp_settings`) sa podstranicom **Upravljanje Poklonima**.
- Ekran za pravila (`includes/view-admin.php`) omogućava kreiranje/izmenu pravila sa prioritetima, opsegom (product, product_cat, brend, linija) i uslovima na korpu.
- AJAX endpoint-i: `pnp_get_products`, `pnp_toggle_active`, `pnp_update_gift_stock`, `pnp_trash_gift_product` za dinamičko upravljanje iz admina.
- Formulari za čuvanje/brisanje koriste `admin_post_pnp_save` i `admin_post_pnp_delete` i zahtevaju capability `manage_woocommerce`.
- Admin assets se učitavaju samo na odgovarajućoj stranici uz nonce (`PNP_NONCE`) za AJAX zahteve.

## 5. Instalacija
- **Upload preko WP admina:** Plugins → Add New → Upload, izaberite `pokloni-plugin.zip`, instalirajte i aktivirajte.
- **Manuelno:** Otvorite `wp-content/plugins/` i raspakujte folder `pokloni-plugin`, zatim aktivirajte dodatak u WP adminu.
- Nakon aktivacije, tabela `pnp_rules` i `pnp_user_gifts` se kreiraju automatski.

## 6. Update sistem (UUPD + GitHub Releases)
- UUPD manifest se nalazi u `/uupd/index.json` i koristi se za isporuku meta podataka o verziji.
- Plugin registruje UUPD na `plugins_loaded` (prioritet 20) sa slugom `pokloni-plugin` i očekuje da release asset nosi naziv **`pokloni-plugin.zip`**.
- `download_url` u manifestu upućuje na GitHub Releases `latest`. Za novi release:
  1. Povećajte verziju u plugin header-u i u `PNP_VERSION`.
  2. Ažurirajte `/uupd/index.json` (`version`, `last_updated`, changelog, download_url) i `/uupd/info.txt`.
  3. Tagujte verziju (npr. `v1.4.0`) i objavite GitHub Release sa asset-om `pokloni-plugin.zip`.
  4. WordPress će povući update preko UUPD i ponuditi instalaciju.

## 7. Razvoj
- Glavni fajl: `pokloni-popusti.php` (učitava sve klase, konstante i UUPD registraciju).
- Logika pravila i rada sa korpom: `includes/pnp-rules.php`.
- Admin ekran i AJAX: `includes/class-pnp-admin.php` + prikazi u `includes/view-admin.php` i `includes/view-gift-manager.php`.
- Helperi i tekstovi: `includes/class-pnp-helpers.php`, `includes/class-pnp-text-manager.php`, `includes/class-pnp-offer-cards.php`, `includes/class-pnp-shortcode.php`.
- Updater drop-in: `includes/updater.php` (UUPD).

## 8. Bezbednost i napomene
- Admin akcije su ograničene na korisnike sa `manage_woocommerce` capability.
- AJAX pozivi u admin delu koriste nonce `PNP_NONCE` (lokalizovan u `pnpAdmin.nonce`).
- Frontend ne dodaje dodatne capability provere van WooCommerce validacija; za specifična ograničenja poklon kategorije koristi se `woocommerce_add_to_cart_validation`.

## 9. Changelog
- **1.4.0** — Pojednostavljen UI bez tabova, čišći rezimei i lakši modal editor.
- **1.3.0** — Modalni multi‑step editor i jasniji rezimei pravila u admin listi.
- **1.2.0** — Admin UX poboljšanja (status, rezimei pravila, sigurnije brisanje) bez izmene rule logike.
- **1.1.0** — Dodata README.md dokumentacija, osvežen UUPD manifest i verzija za GitHub Releases.
- **1.0.23** — Ažurirani UUPD metapodaci i manifest za testiranje update mehanizma.
