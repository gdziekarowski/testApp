{{-- Każdy link w strefie panelu niesie podpisany kontekst instalacji (idosell_route). --}}
<nav style="display: flex; gap: 16px; margin-bottom: 24px; font-size: 14px;">
    <a id="nav-panel" href="{{ idosell_route('app.panel') }}" style="color: #2563eb;">Sklepy</a>
    <a id="nav-installation" href="{{ idosell_route('app.installation') }}" style="color: #2563eb;">Instalacja</a>
</nav>
