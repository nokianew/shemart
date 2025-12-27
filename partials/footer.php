  </div>
</main>

<footer class="bg-light mt-5 border-top">
  <div class="container py-3 text-center small text-muted">
    &copy; <?= date('Y'); ?> <?= htmlspecialchars($site_name); ?> – Curated for women.
  </div>
</footer>

<?php
// WhatsApp floating button (frontend only)
if (!isset($is_admin) || !$is_admin) {
    global $whatsapp_number, $whatsapp_default_message, $site_name;

    if (!empty($whatsapp_number)) {
        $msg   = $whatsapp_default_message ?: "Hi $site_name, I need some help.";
        $waText = urlencode($msg);
        $waUrl  = "https://wa.me/$whatsapp_number?text=$waText";
        ?>
        <div class="whatsapp-float">
          <a href="<?= $waUrl; ?>" target="_blank" rel="noopener" aria-label="Chat on WhatsApp">
            <!-- Small WhatsApp SVG icon -->
            <svg viewBox="0 0 32 32" aria-hidden="true">
              <path d="M16 3C9.4 3 4.1 8.2 4.1 14.8c0 2.6.9 4.9 2.4 6.8L5 27l5.6-1.5c1.8 1 3.9 1.6 6.1 1.6 6.6 0 11.9-5.3 11.9-11.9C28.6 8.2 23.3 3 16.7 3H16zm0 2.3h.6c5.4.3 9.6 4.7 9.6 10.1 0 5.6-4.5 10.1-10.1 10.1-2 0-3.9-.6-5.5-1.6l-.4-.2-3.3.9.9-3.2-.2-.4c-1.2-1.6-1.9-3.6-1.9-5.7C6.7 10 10.9 5.6 16.4 5.3H16zm-4.1 5c-.3 0-.8.1-1.2.6-.4.4-1.6 1.5-1.6 3.7 0 2.2 1.6 4.3 1.8 4.6.2.3 3.1 4.9 7.7 6.6 3.8 1.5 4.5 1.2 5.3 1.1.8-.1 2.6-1.1 3-2.2.4-1.1.4-2.1.3-2.3-.1-.2-.4-.3-.8-.5s-2.6-1.3-3-1.4c-.4-.2-.7-.2-1 .2-.3.4-1.2 1.4-1.4 1.7-.2.3-.5.3-1 .1-.5-.3-2-1-3.8-2.5-1.4-1.2-2.3-2.7-2.6-3.1-.3-.4 0-.6.2-.8.2-.2.4-.5.6-.7.2-.2.3-.4.4-.6.1-.2 0-.5 0-.7-.1-.2-1-2.5-1.4-3.4-.3-.8-.7-.8-1-.8z"/>
            </svg>
          </a>
        </div>
        <?php
    }
}
?>

<!-- Bootstrap JS (CDN) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
