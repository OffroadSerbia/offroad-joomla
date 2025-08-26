# OffroadStage - Joomla system plugin

Svrha: označiti staging domen(e) kao `noindex, nofollow` i poslati `X-Robots-Tag` header.

Instalacija (Joomla 4/5):

1. Napravi ZIP od foldera `offroadstage` (mora da sadrži `offroadstage.php`, `offroadstage.xml`, `language/`).
2. U Joomla Administrator → System → Extensions → Install → Upload Package File → izaberi taj ZIP.
3. Idi na Plugins, pronađi "Offroad Serbia - Staging Guard" i uključi ga.
4. U podešavanjima unesi staging domene (po jedan po redu ili zarezima), npr: `staging.offroadserbia.com`.

Napomene:

- Plugin radi samo na "Site" (frontend) klijentu.
- Ne utiče na produkciju, osim ako domen poklapa neki unos.
- Fallback header pomaže i za non-HTML odgovore.
