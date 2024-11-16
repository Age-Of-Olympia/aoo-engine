$(document).ready(function() {


    $('.journal td').click(function() {


        var currentRow = $(this).closest('tr');

        if ($(this).data('page') === 'right') {


            var nextRow = currentRow.next('tr');

            if (nextRow.length) {


                currentRow.hide();
                nextRow.show();
            }


        }

        else if ($(this).data('page') === 'left') {


            var prevRow = currentRow.prev('tr');

            if (prevRow.length) {


                currentRow.hide();
                prevRow.show();
            }
        }
    });
});
