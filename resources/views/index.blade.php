<!DOCTYPE html>
<html lang="en" dir="ltr">
  <head>
    <meta charset="utf-8">
    <title></title>
  </head>

  <style>
  body{
    width:100%;
    padding:0;
    margin:0;
  }

  .threads{
    width: 90%;
    min-width: 320px;
    margin: 0 auto;
  }

  td{
    vertical-align: -webkit-baseline-middle;
  }

  .media-container{
    display: flex;
    flex-wrap: wrap;
  }

  @media only screen and (min-width: 768px){
    .threads{
      width:750px;
    }
  }

  @media only screen and (min-width: 992px){
    .threads{
      width: 970px;
    }
  }
  </style>

  <body>
    <h1 style="text-align:center;">Conversations</h1>
    <table class="threads" border="1" style="margin:0 auto;">
          <thead>
            <tr>
              <th class="row-1 row-ID">ID</th>
              <th class="row-4 row-email">Message</th>
              <th class="row-2 row-name">Author</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($threads as $key => $thread)
              <tr>
                <td>{{$thread->id}}</td>
                <td>{!!($thread->message)!!}</td>
                <td>{{$thread->author}}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
  </body>
</html>
