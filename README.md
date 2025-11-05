# Tech Hub - E-commerce

## Avvio Rapido

### Prerequisiti
- Docker Desktop installato e avviato
- Porte libere: 8080, 3307, 8081

### Avvio del Progetto
```bash
docker-compose up -d --build
```

### Accesso all'Applicazione
- **Sito web**: http://localhost:8080
- **phpMyAdmin**: http://localhost:8081
  - Server: `db`
  - Username: `tech_hub_user`
  - Password: `tech_hub_pass_2024`

### Credenziali di Test

**Account Amministratore:**
- Email: `admin@gmail.com`
- Password: `Admin450%`

**Account Utente Standard:**
- Email: `mario.rossi@gmail.com`
- Password: `MarioRossi15$`

### Arresto del Progetto
```bash
docker-compose down
```

Per rimuovere anche i dati del database:
```bash
docker-compose down -v
```
---

## Database

**Nome Database**: `tech_hub_db`

### Tabelle Principali (13)

**Utenti e Sicurezza (4)**
- `ruoli` - Ruoli utente (admin, utente)
- `utenti` - Account utenti
- `utenti_ruoli` - Assegnazione ruoli
- `tentativi_autenticazione` - Log accessi

**Catalogo Prodotti (4)**
- `categorie` - Categorie prodotti
- `prodotti` - Prodotti in vendita
- `prodotti_categorie` - Relazione molti-a-molti
- `immagini_prodotti` - Immagini dei prodotti

**Carrello e Ordini (5)**
- `carrelli` - Carrelli utenti
- `articoli_carrello` - Articoli nel carrello
- `indirizzi` - Indirizzi spedizione/fatturazione
- `ordini` - Ordini completati
- `articoli_ordine` - Dettaglio articoli ordinati

---

## Funzionalità Principali

### Utente Standard
- ✅ Registrazione con validazione password forte
- ✅ Login con protezione anti-brute force
- ✅ Catalogo prodotti con filtri e ricerca
- ✅ Gestione carrello (aggiungi, modifica, rimuovi)
- ✅ Checkout con gestione indirizzi
- ✅ Export dati personali (GDPR)
- ✅ Eliminazione account

### Amministratore
- ✅ Dashboard con statistiche real-time
- ✅ Monitoraggio tentativi di accesso
- ✅ Gestione account bloccati
- ✅ Visualizzazione ordini con filtri temporali
- ✅ Verifica integrità immagini prodotti

---

## Test e Verifica

### Scenario di Test Consigliato

1. **Registrazione nuovo utente** su http://localhost:8080/registrati.php
2. **Login** con le credenziali create
3. **Navigazione catalogo** e ricerca prodotti
4. **Aggiunta prodotti al carrello**
5. **Checkout** con creazione nuovo indirizzo
6. **Logout** e **login come admin** (`admin@gmail.com`)
7. **Verifica ordine** nel pannello amministrativo

### Verifica Sicurezza

- Tentare 5 login falliti → Verifica blocco account
- Controllare log in `tentativi_autenticazione`
- Sbloccare manualmente l'account da pannello admin

---

## Conformità GDPR

Il sistema implementa:
- ✅ Export completo dati utente in formato CSV
- ✅ Cancellazione totale account con CASCADE
- ✅ Informativa privacy accessibile
- ✅ Consenso esplicito termini e condizioni