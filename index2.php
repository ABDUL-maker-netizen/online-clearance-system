<!DOCTYPE html>

<html lang="en"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Alumni Relation Division Office Dashboard</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet"/>
<style data-purpose="custom-styles">
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
    body {
      font-family: 'Inter', sans-serif;
      background-color: #f8fafc; /* Light slate background */
    }
  </style>
<script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            primary: {
              50: '#eff6ff',
              100: '#dbeafe',
              500: '#3b82f6',
              600: '#2563eb',
              700: '#1d4ed8',
              800: '#1e40af',
              900: '#1e3a8a',
            }
          }
        }
      }
    }
  </script>
</head>
<body class="text-slate-800 antialiased min-h-screen">
<!-- BEGIN: MainHeader -->
<header class="bg-white border-b border-slate-200 px-6 py-4 sticky top-0 z-10 shadow-sm">
<div class="max-w-7xl mx-auto flex flex-col md:flex-row items-center justify-between gap-4">
<!-- Brand & Title -->
<div class="flex items-center gap-4">
<div class="bg-primary-50 text-primary-600 p-2 rounded-lg">
<i class="fa-solid fa-building text-xl"></i>
</div>
<div>
<div class="flex items-center gap-3">
<h1 class="text-xl md:text-2xl font-bold text-slate-900 tracking-tight">ALUMNI RELATION DIVISION OFFICE</h1>
<span class="inline-flex items-center rounded-full bg-primary-100 px-2.5 py-0.5 text-xs font-semibold text-primary-800">
              2 Students
            </span>
</div>
<p class="text-xs font-medium text-slate-500 uppercase tracking-wider mt-0.5">Dashboard</p>
</div>
</div>
<!-- Action Buttons -->
<div class="flex items-center gap-3">
<button class="inline-flex items-center justify-center gap-2 rounded-md bg-white px-4 py-2 text-sm font-medium text-primary-700 shadow-sm ring-1 ring-inset ring-primary-300 hover:bg-primary-50 transition-colors">
<i class="fa-solid fa-arrow-up-from-bracket"></i>
          Upload Signature
        </button>
<button class="inline-flex items-center justify-center gap-2 rounded-md bg-teal-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-teal-700 transition-colors">
<i class="fa-solid fa-file-lines"></i>
          Reports
        </button>
<button class="inline-flex items-center justify-center gap-2 rounded-md bg-red-50 text-red-700 px-4 py-2 text-sm font-medium shadow-sm ring-1 ring-inset ring-red-200 hover:bg-red-100 transition-colors">
<i class="fa-solid fa-arrow-right-from-bracket"></i>
          Logout
        </button>
</div>
</div>
</header>
<!-- END: MainHeader -->
<!-- BEGIN: Main Content -->
<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
<!-- Table Container -->
<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
<!-- Table Toolbar -->
<div class="px-6 py-4 border-b border-slate-200 bg-slate-50 flex items-center justify-between">
<div class="flex items-center gap-3">
<input class="h-4 w-4 rounded border-slate-300 text-primary-600 focus:ring-primary-600 cursor-pointer" id="selectAll" type="checkbox"/>
<label class="text-sm font-medium text-slate-700 cursor-pointer select-none" for="selectAll">Select All Pending</label>
</div>
<div class="flex items-center gap-4">
<span class="text-sm text-slate-500">0 students selected</span>
<button class="inline-flex items-center justify-center gap-2 rounded-md bg-primary-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-primary-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
<i class="fa-solid fa-eye"></i>
            Preview &amp; Approve
          </button>
</div>
</div>
<!-- Data Table -->
<div class="overflow-x-auto">
<table class="w-full text-left border-collapse">
<thead>
<tr class="bg-slate-900 text-white text-xs uppercase tracking-wider">
<th class="px-6 py-4 font-semibold w-12 text-center" scope="col">
<!-- Checkbox placeholder for column alignment -->
</th>
<th class="px-6 py-4 font-semibold w-24" scope="col">ID</th>
<th class="px-6 py-4 font-semibold" scope="col">Name</th>
<th class="px-6 py-4 font-semibold" scope="col">Reg No</th>
<th class="px-6 py-4 font-semibold" scope="col">Status</th>
<th class="px-6 py-4 font-semibold" scope="col">Documents</th>
<th class="px-6 py-4 font-semibold text-right" scope="col">Action</th>
</tr>
</thead>
<tbody class="divide-y divide-slate-200">
<!-- Row 1 -->
<tr class="hover:bg-slate-50 transition-colors">
<td class="px-6 py-5 text-center align-top">
<input class="h-4 w-4 rounded border-slate-300 text-primary-600 focus:ring-primary-600 cursor-pointer mt-1" type="checkbox"/>
</td>
<td class="px-6 py-5 text-sm font-semibold text-slate-900 align-top">6</td>
<td class="px-6 py-5 text-sm text-slate-700 font-medium align-top">Abdul Abdul</td>
<td class="px-6 py-5 text-sm text-slate-500 align-top">20/59199U/1</td>
<td class="px-6 py-5 align-top">
<span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-200">
<i class="fa-solid fa-circle-check"></i>
                  Approved
                </span>
</td>
<td class="px-6 py-5 align-top">
<div class="space-y-2">
<a class="inline-flex items-center gap-2 text-sm text-primary-600 hover:text-primary-800 bg-primary-50 px-2 py-1 rounded-md w-fit" href="#">
<i class="fa-solid fa-file-pdf text-primary-500"></i>
                    Main File
                  </a>
<div class="flex items-center gap-2 text-sm text-slate-600">
<i class="fa-solid fa-paperclip text-slate-400"></i>
                    student_6.png
                  </div>
<div class="flex items-center gap-2 text-sm text-slate-500 italic">
<i class="fa-regular fa-comment text-slate-400"></i>
                    good
                  </div>
</div>
</td>
<td class="px-6 py-5 text-right align-top">
<button class="inline-flex items-center justify-center gap-2 rounded-md bg-white px-3 py-1.5 text-sm font-medium text-primary-700 shadow-sm ring-1 ring-inset ring-primary-300 hover:bg-primary-50 transition-colors">
<i class="fa-solid fa-eye"></i>
                  Review
                </button>
</td>
</tr>
<!-- Row 2 -->
<tr class="hover:bg-slate-50 transition-colors">
<td class="px-6 py-5 text-center align-top">
<input class="h-4 w-4 rounded border-slate-300 text-primary-600 focus:ring-primary-600 cursor-pointer mt-1" type="checkbox"/>
</td>
<td class="px-6 py-5 text-sm font-semibold text-slate-900 align-top">5</td>
<td class="px-6 py-5 text-sm text-slate-700 font-medium align-top">Abduls Abduls</td>
<td class="px-6 py-5 text-sm text-slate-500 align-top">20/59188U/1</td>
<td class="px-6 py-5 align-top">
<span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-200">
<i class="fa-solid fa-circle-check"></i>
                  Approved
                </span>
</td>
<td class="px-6 py-5 align-top">
<div class="space-y-2">
<a class="inline-flex items-center gap-2 text-sm text-primary-600 hover:text-primary-800 bg-primary-50 px-2 py-1 rounded-md w-fit" href="#">
<i class="fa-solid fa-file-pdf text-primary-500"></i>
                    Main File
                  </a>
<div class="flex items-center gap-2 text-sm text-slate-600">
<i class="fa-solid fa-paperclip text-slate-400"></i>
                    IMG-20260609-WA0040.jpg
                  </div>
<div class="flex items-center gap-2 text-sm text-slate-500 italic">
<i class="fa-regular fa-comment text-slate-400"></i>
                    good
                  </div>
</div>
</td>
<td class="px-6 py-5 text-right align-top">
<button class="inline-flex items-center justify-center gap-2 rounded-md bg-white px-3 py-1.5 text-sm font-medium text-primary-700 shadow-sm ring-1 ring-inset ring-primary-300 hover:bg-primary-50 transition-colors">
<i class="fa-solid fa-eye"></i>
                  Review
                </button>
</td>
</tr>
</tbody>
</table>
</div>
<!-- Table Footer / Pagination -->
<div class="px-6 py-4 border-t border-slate-200 bg-slate-50 flex items-center justify-between text-sm text-slate-500">
<div>
          Showing <span class="font-semibold text-slate-900">1</span> to <span class="font-semibold text-slate-900">2</span> of <span class="font-semibold text-slate-900">2</span> entries
        </div>
<div class="flex items-center gap-2">
<i class="fa-solid fa-users text-slate-400"></i>
          Total Students: <span class="font-semibold text-slate-900">2</span>
</div>
</div>
</div>
</main>
<!-- END: Main Content -->
</body></html>