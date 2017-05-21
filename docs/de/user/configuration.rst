Konfiguration
=============

Nun, da du eingeloggt bist, ist es Zeit, deinen Account so zu konfigurieren,
wie du möchtest.

Klicke auf ``Konfiguration`` im Menü. Du hast fünf Karteireiter: ``Einstellungen``,
``RSS``, ``Benutzer-Informationen``, ``Kennwort`` und ``Tagging-Regeln``.

Einstellungen
-------------

Theme
~~~~~

wallabag ist anpassbar. Du kannst dein bevorzugtes Theme hier auswählen. Das Standardtheme
ist ``Material``, es ist das Theme, dass in den Dokumentationsbildschirmfotos genutzt wird.

Artikel pro Seite
~~~~~~~~~~~~~~~~~

Du kannst die Anzahl der dargestellten Artikel pro Seite ändern.

Lesegeschwindigkeit
~~~~~~~~~~~~~~~~~~~

wallabag berechnet die Lesezeit für jeden Artikel. Du kannst hier definieren, dank dieser Liste, ob du
ein schneller oder langsamer Leser bist. wallabag wird die Lesezeit für jeden Artikel neu berechnen.

Wohin möchtest du weitergeleitet werden, nach dem ein Artikel als gelesen markiert wurde?
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

Jedes Mal, wenn du eine Aktion ausführst (nach dem Markieren eines Artikels als gelesen oder Favorit, nach dem Löschen eines Artikels oder dem Entfernen eines Tag von einem Eintrag), kannst du weitergeleitet werden:

- zur Homepage
- zur aktuellen Seite

Sprache
~~~~~~~

Du kannst die Sprache von der wallabag Benutzeroberfläche ändern.

RSS
---

wallabag stellt RSS Feeds für jeden Artikelstatus bereit: ungelesen, Favoriten und Archiv.

Als erstes musst du einen persönlciehn Token erstellen: Klicke auf ``Token generieren``.
Es ist möglich deinen Token zu ändern, indem du auf ``Token zurücksetzen`` klickst.

Jetzt hast du drei Links, einen für jeden Status: Füge sie in deinem liebsten Feedreader hinzu.

Du kannst auch definieren wie viele Artikel du in deinem RSS Feed (Standardwert: 50) haben willst.

There is also a pagination available for these feeds. You can add ``?page=2`` to jump to the second page.
The pagination follow `the RFC <https://tools.ietf.org/html/rfc5005#page-4>`_ about that, which means you'll find the ``next``, ``previous`` & ``last`` page link inside the `<channel>` tag of each RSS feed.

Benutzer-Informationen
----------------------

Du kannst deinen Namen ändern, deine E-Mail-Adresse und die Zwei-Faktor-Authentifizierung aktivieren.

Zwei-Faktor-Authentifizierung (2FA)
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    Die Zwei-Faktor-Authentifizierung (2FA) dient dem Identitätsnachweis eines Nutzers mittels der
    Kombination zweier verschiedener und insbesondere unabhängiger Komponenten (Faktoren).

    https://de.wikipedia.org/wiki/Zwei-Faktor-Authentifizierung

**Warnung:** Das Aktivieren von 2FA über das Konfigurations-Interface ist nur möglich, wenn vorher in der `app/config/parameters.yml` die `twofactor_auth`-Eigenschaft auf `true` gesetzt wurde (nach der Konfiguration das Leeren des Cache mit `php bin/console cache:clear -e=prod` nicht vergessen).

Wenn du 2FA aktivierst, erhälst du jedes Mal, wenn du dich bei wallabag einloggen willst, einen Code per
Mail. Du musst den Code in das folgende Formular eingeben.

.. image:: ../../img/user/2FA_form.png
    :alt: Zwei-Faktor-Authentifizierung
    :align: center

Wenn du nicht jedes Mal, wenn du dich einloggen willst, einen Code zugesendet bekommen möchtest, kannst du
die Checkbox ``Ich bin an einem persönlichen Computer`` anhaken: wallabag wird sich an dich für 15 Tage
erinnern.

Passwort
--------

Du kannst dein Passwort hier ändern (8 Zeichen Minimum).

Tagging-Regeln
--------------

Wenn du automatisch einen Tag zu einem neuen Artikel zuweisen lassen möchtest, ist dieser Teil der
Konfiguration, was du suchst.

Was ist mit Tagging-Regeln gemeint?
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

Dies sind Regeln, die von wallabag genutzt werden, um neue Artikel automatisch zu taggen
Jedes Mal, wenn ein neuer Artikel hinzugefügt wird, werden alle Tagging-Regeln genutzt, um deine
konfigurierten Tags hinzuzufügen, folglich um dir den Aufwand zu sparen, die Artikel manuell einzuteilen.

Wie benutze ich sie?
~~~~~~~~~~~~~~~~~~~~

Nehmen wir an, du möchtest neuen Artikeln einen Tag *schnell gelesen*, wenn du die Lesezeit kleiner als
3 Minuten ist.
In diesem Fall solltest du in das Regelfeld "readingTime <= 3" eintragen und *schnell gelesen* in das Tags-Feld.
Mehrere Tags können gleichzeitig hinzugefügt werden, wenn man sie mit einem Komma trennt:
*schnell gelesen, Pflichtlektüre*.
Komplexe Regeln können mit vordefinierten Operatoren geschrieben werden:
Wenn *readingTime >= 5 AND domainName = "github.com"*, dann tagge als *lange zu lesen, github*.

Welche Variablen und Operatoren kann ich zum Regeln schreiben nutzen?
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

Die folgenden Variablen und Operatoren können genutzt werden, um Tagging-Regeln zu erstellen (sei vorsichtig, denn bei einigen Werten musst du Anführungszeichen hinzufügen, z.B. ``language = "de"``):

===========  ==============================================  ========  ==========
Variable     Bedeutung                                       Operator  Bedeutung
-----------  ----------------------------------------------  --------  ----------
title        Titel des Artikels                              <=        Kleiner gleich als…
url          URL des Artikels                                <         Kleiner als…
isArchived   Ob der Artikel archiviert ist oder nicht        =>        Größer gleich als…
isStarred    Ob der Artikel favorisiert ist oder nicht       >         Größer als…
content      Inhalt des Eintrags                             =         Gleich zu…
language     Sprache des Eintrags                            !=        Nicht gleich zu…
mimetype     MIME-Typ des Eintrags                           OR        Eine Regel oder die andere
readingTime  Die geschätzte Lesezeit in Minuten              AND       Eine Regel und die andere
domainName   Der Domain-Name des Eintrags                    matches   Testet, dass ein Feld einer Suche (unabhängig von Groß- und Kleinschreibung) übereinstimmt. Z.B.: title matches "Fußball"
===========  ==============================================  ========  ==========
