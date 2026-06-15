<footer
    style="background: #1a3c34; border-top: 1px solid rgba(255,255,255,0.1); padding: 16px 32px; text-align: center; font-size: 12px; color: rgba(255,255,255,0.7); position: sticky; bottom: 0; z-index: 99; margin-top: auto;">
    <span>© 2026 Cyber Directorate HRMS | Version 2.0</span>
    <!-- jQuery (required) -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/nepali.datepicker.v4.0.8.min.js"></script>

    <script>
        $(document).ready(function () {
            $('#contactTable').DataTable({
                pageLength: 10,
                lengthMenu: [10, 25, 50, 100],
                ordering: true,
                searching: true,
                responsive: true,
                language: {
                    search: "Search:",
                    lengthMenu: "Show _MENU_ Records",
                    info: "Showing _START_ to _END_ of _TOTAL_ records",
                    paginate: {
                        first: "पहिलो",
                        last: "अन्तिम",
                        next: "→",
                        previous: "←"
                    }
                }
            });
        });


        // for blood group modal jQuery
        $(document).on('click', '.blood-btn', function () {
            let group = $(this).data('group');

            // Bootstrap 5 correct modal trigger
            let modalEl = document.getElementById('bloodModal');
            let bloodModal = new bootstrap.Modal(modalEl);

            bloodModal.show();

            $('#bloodModalBody').html("Loading...");

            $.ajax({
                url: 'ajax/fetch_blood_members.php',
                type: 'POST',
                data: { blood_group: group },
                success: function (response) {
                    console.log("SUCCESS:", response);
                    $('#bloodModalBody').html(response);
                },
                error: function (xhr, status, error) {
                    console.log("AJAX ERROR:", xhr.responseText);
                    console.log("STATUS:", status);
                    console.log("ERROR:", error);

                    $('#bloodModalBody').html("Error loading data");
                }
            });
        });
// datatable initialize 
        $('.datatable').DataTable({
            responsive: true
        });

// Initialize the nepali datepicker  
// $('.nepali-datepicker').each(function() {
//     $(this).nepaliDatePicker();
// });  
$(document).ready(function () {
    $('.nepali-datepicker').nepaliDatePicker();
});  

    </script>
</footer>