<div class="wrap hs_form">	

	<h2>Scrapping News, Events and Faculty Staff from https://scm.hsu.edu.hk</h2>

    <!-- Forms are NOT created automatically, so you need to wrap the table in one to use features like bulk actions -->

    <form>

        <!-- For plugins, we also need to ensure that the form posts back to our current page -->

		<div class="form-wrap">

			<label>Select Data Type : </label>

			<select name="hs_data" id="hs_data">
				<option value="news">News</option>
				<option value="events">Events</option>
				<!-- <option value="staff">Faculty Staff</option> -->
			</select>

		</div>

		<div class="form-wrap" id="page_range">

			<label>Pages : </label>

			<input type="number" min="1" step="1" name="hs_page_start" value="1"/> ~ <input type="number" min="1" step="1" name="hs_page_end" value="1"/>

		</div>	

		<div class="form-wrap" id="news_year">

			<label>Select year : </label>

			<select name="hs_year" id="hs_year">

			</select>

		</div>


		<h5 id="title" style="display:none;"></h5>

		<div class="progress" style="display:none;">

			<div class="progress_bar"><span>1/100</span></div>

		</div>

		<div class="form-wrap buttons">

			<button type="button" class="button button-primary" id="btnScrape">Start Scrapping News</button>

		</div>

    </form>
</div>