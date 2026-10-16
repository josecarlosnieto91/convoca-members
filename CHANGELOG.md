## [2.8.89] — 2026-10-16

### Arreglado
- **2.8.88 se publicó con un aviso de PHPStan** (`method_exists()` sobre la API del gateway: el doble del
  análisis lo declara, así que sobra). Retirada la comprobación redundante: la guarda que importa —que el
  gateway esté instalado— sigue puesta con `class_exists()`. El comportamiento no cambia.

