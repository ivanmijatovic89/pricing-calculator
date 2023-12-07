# Dokumentacija o Izračunavanju Cene za Apartmane

## Opis
Ovaj dokument opisuje postupak automatskog izračunavanja cena za apartmane korišćenjem Laravel Artisan komandi.

## Komande
1. Izračunavanje Cena za Sve Apartmane
Komanda: php artisan compute-price-for-all-apartments
Funkcija: Vrši iteraciju kroz sve apartmane i pokreće komandu compute-price-for-apartment {apartmentId} za svaki apartman. Ova komanda izračunava cenu po osobi po danu za svaki apartman za narednih 760 dana.
Periodično Pokretanje: Komanda treba da se izvršava periodično, po izboru na svakih 4, 8 ili 12 sati.

2. Izračunavanje Cena za Ažurirane Apartmane
Komanda: php artisan compute-price-for-updated-apartments
Funkcija: Izračunava cene za apartmane koji su imali promene u poslednjih 15 minuta.
Implementacija u Kernel.php: Treba je postaviti da se pokreće svakog minuta.

## Kako Funkcioniše?
Svaki put kada korisnik ažurira neki od parametara važnih za izračunavanje cene, apartman se dodaje u tabelu pending_apartments_for_computed_pricing.
Sistem na svakih minut proverava ovu tabelu i ažurira cene za apartmane koji se nalaze u njoj.
Postoji odlaganje od 15 minuta kako bi se izbegla prečesta ažuriranja istog apartmana.
