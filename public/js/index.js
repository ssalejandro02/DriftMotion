$('.add_favorite').click(function () {
    var favorite = event.target;
    post_id = favorite.id;

    Swal.fire({
        title: 'Agregar a favoritos?',
        showCancelButton: true,
        confirmButtonText: '¡Sí,Agregalo!',
        cancelButtonText: 'No, cancelar!',
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                type: 'POST',
                url: Routing.generate('postSave', { id: post_id }),
                data: ({post_id: post_id}),
                async: true,
                dataType: "json",
                success: function (data) {
                    Swal.fire(
                        '¡Agregado!',
                        'Ahora este post está en tus favoritos',
                        'success'
                    )
                }
            })
        } else if (result.dismiss === Swal.DismissReason.cancel) {
            document.getElementById(user_id).checked = !clase.checked;
            Swal.fire(
                'Cancelado',
                'No se ha agregado a favoritos',
                'error'
            )
        }
    })
})

$('#delete').click(function () {
    Swal.fire({
        title: '¿Deseas eliminar el Post?',
        showCancelButton: true,
        cancelButtonText: 'Cancelar',
        confirmButtonText: 'Eliminar'
    }).then((result) => {
        if (result.isConfirmed) {
            var deleteUrl = $(this).data("url");

            $.ajax({
                url: deleteUrl,
                type: 'POST',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            title: 'Eliminado',
                            text: response.message,
                            icon: 'success',
                            timer: 2500,
                            showConfirmButton: false
                        }).then(() => {
                            window.location.href = '/';
                        })
                    } else {
                        Swal.fire({
                            title: 'Error',
                            text: response.message,
                            icon: 'error'
                        })
                    }
                },
                error: function() {
                    Swal.fire({
                        title: 'Error',
                        text: 'Hubo un problema al intentar eliminar el post.',
                        icon: 'error'
                    })
                }
            })
        }
    })
})

$('#delete-sm').click(function (){
    var post_id = $(this).attr("data-id")
    Swal.fire({
        title: '¿Deseas eliminar el Post?',
        showCancelButton: true,
        cancelButtonText: 'Cancelar',
        confirmButtonText: 'Eliminar'
    }).then((result) => {
        /* Read more about isConfirmed, isDenied below */
        if (result.isConfirmed) {
            window.location.href = Routing.generate('postDelete', { id: post_id })
        }
    })
})