<?php

// Configurarea bazei de date folosita de aplicatie.
// In aceasta instalare se foloseste SQLite local, deci MySQL din XAMPP nu este necesar.
return array (
  'driver' => 'pdo_sqlite', #baza de date sqlite accesata prin pdo
  'path' => 'C:\\xampp\\htdocs\\proiectpw\\src\\Database/../../storage/database.sqlite', #calea catre fisierul bazei de date
);
