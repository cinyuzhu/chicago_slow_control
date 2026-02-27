<script>
    // Save scroll position before unload
    window.addEventListener("beforeunload", function() {
        localStorage.setItem("scrollY", window.scrollY);
    });

    // Restore scroll position after load
    window.addEventListener("load", function() {
        const y = localStorage.getItem("scrollY");
        if (y !== null) {
            window.scrollTo(0, parseInt(y, 10));
        }
    });
</script>