$('button.fulfillment').on('click',function(){
  let button = $(this);
  let urlRequest = $(button).data( "ajax" );
  $.ajax({
    url: urlRequest,
    context: document.body
  })
  .done(function() {
    $(button).html( "Done" );
  })
  .fail(function(error) {
    console.log(error);
  });
})
