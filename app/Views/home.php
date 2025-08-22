<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Dictionary Form</title>

    <link href="/css/app.css" rel="stylesheet" />
    <link href="/css/styles.css" rel="stylesheet" />

</head>
<body class="min-h-screen bg-gray-100">
<header class="bg-blue-600 text-white p-4 text-xl font-bold">
    My Header
</header>

<main class="grid grid-cols-4 gap-4 p-4">

  <!-- Column 1 -->
  <section class="bg-white p-4 rounded shadow">
    Column 1
  </section>

  <!-- Columns 2 and 3 (combined) -->
  <section class="col-span-2 bg-white p-4 rounded shadow flex flex-col gap-4">

    <div>
    <table class="w-full table-fixed border-collapse">
    <tbody id="input-body" class="space-y-2">
        <tr>
            <td class="w-9/12 p-0 m-0 border-none">
              <input
                type="text"
                id="filter-input"
                placeholder="Type something..."
                class="w-full p-2 border border-gray-300 rounded"
              />
            </td>
            <td class="w-1/12 p-0 m-0 border-none text-center">
            <input type="checkbox" id="filter-checkbox" />
            </td> <!-- gap2, empty for alignment -->
            <td class="w-1/12 text-center p-0 m-0 border-none"></td> <!-- gapBin icon -->
            <td class="w-1/12 text-center p-0 m-0 border-none"></td> <!-- gapEdit icon -->
            <td class="w-[40px] p-2 text-center">
            </td>
          </tr>
          <!-- Data rows inserted via JS -->
        </tbody>
    </table>
</div>
    <div id="scroll-container" class="h-[600px] overflow-y-scroll no-scrollbar" data-mode="initial">
    
    <table class="w-full table-fixed border-separate border-spacing-y-2">
        
          
      
        <tbody id="word-table-body" class="divide-y divide-gray-200">
        
          <!-- Data rows inserted via JS -->
        </tbody>
      </table>
    </div>

    <div>
    <table class="w-full table-fixed border-collapse">
    <tbody id="update-body" class="space-y-2">
    <tr>
    <td class="w-4/12 p-0 m-0 border-none"><input
                type="text"
                id="update-input"
                class="w-full p-2 border border-gray-300 rounded"
              /></td>
    <td class="w-1/12 p-0 m-0 border-none"></td>
    <td class="w-4/12 p-0 m-0 border-none"><input
                type="text"
                id="update-input_2"
                class="w-full p-2 border border-gray-300 rounded"
              /></td>
    <td class="w-1/12 p-0 m-0 border-none"></td>

    <td class="w-2/12 p-0 m-0 border-none">
    <div id="save-message" class="text-red-600 mb-2"></div>
    <button
        type="button"
        id="save-btn"
        class="w-full p-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition">
        Save
    </button>
    </td>
    <td class="w-[40px] p-2 text-center">
    </td>
</tr>
</tbody>
</table>
</div>
  </section>

  <!-- Column 4 -->
  <section class="bg-white p-4 rounded shadow">
    Column 4
  </section>

</main>

<script type="module" src="/js/home_scroll.js" defer></script>
</body>
</html>