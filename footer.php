</main> </div> <script>
        function updateClock() {
            const now = new Date();
            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit' };
            // Format waktu Indonesia
            const timeString = now.toLocaleDateString('id-ID', options).replace(/\./g, ':');
            document.getElementById('live-clock').innerText = timeString;
        }
        setInterval(updateClock, 1000);
        updateClock(); // Jalankan langsung saat pertama dimuat
    </script>
</body>
</html>