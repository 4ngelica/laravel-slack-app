<h1 style="text-align:center;">Conversations</h1>
<table class="threads" border="1" width="90%" style="margin:0 auto;">
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
            <td>{{$thread->message}}</td>
            <td>{{$thread->author}}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
