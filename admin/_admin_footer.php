<?php
// --------------------------------------
// Admin Footer (Shared)
// --------------------------------------
?>

    </div> <!-- /.container -->

    <!-- Optional: Toast / Flash auto-hide -->
    <script>
      document.addEventListener('DOMContentLoaded', () => {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
          setTimeout(() => {
            alert.classList.add('fade');
            setTimeout(() => alert.remove(), 500);
          }, 4000);
        });
      });
    </script>

    <!-- Bootstrap JS (only once, footer is safest place) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Global Admin JS Hook (optional future use) -->
    <script>
      window.ADMIN = {
        csrf: <?= json_encode($_SESSION['csrf_token'] ?? '') ?>,
        isSuper: <?= json_encode(!empty($_SESSION['admin']['is_super'])) ?>,
        role: <?= json_encode($_SESSION['admin']['role'] ?? '') ?>
      };
    </script>

</body>
</html>
