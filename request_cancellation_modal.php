<?php
// request_cancellation_modal.php
if (!isset($_GET['appointment_id'])) exit;
$appointment_id = intval($_GET['appointment_id']);
?>
<div class="modal fade" id="globalCancelModal" tabindex="-1" aria-labelledby="globalCancelModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="request_cancellation.php">
        <div class="modal-header">
          <h5 class="modal-title" id="globalCancelModalLabel">Request Cancellation</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="appointment_id" value="<?php echo $appointment_id; ?>">
          <div class="mb-3">
            <label for="cancellation_reason" class="form-label">Reason for cancellation</label>
            <textarea name="cancellation_reason" id="cancellation_reason" class="form-control" required placeholder="Enter your reason here..."></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-danger">Submit Request</button>
        </div>
      </form>
    </div>
  </div>
</div>
