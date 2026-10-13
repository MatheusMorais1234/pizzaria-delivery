        </main>
    </div><!-- fim flex-1 -->
</div><!-- fim flex min-h-screen -->

<script>
function toggleSidebar() {
    const sb = document.getElementById('sidebar');
    const ov = document.getElementById('overlay');
    sb.classList.toggle('closed');
    ov.classList.toggle('hidden');
}
// Fecha sidebar ao clicar em link (mobile)
document.querySelectorAll('#sidebar a').forEach(a => {
    a.addEventListener('click', () => {
        if (window.innerWidth < 768) toggleSidebar();
    });
});
</script>
</body>
</html>
