require "json"
require "selenium-webdriver"
require "rspec"
include RSpec::Expectations

describe "CreateCharge" do

  before(:each) do
    @driver = Selenium::WebDriver.for :firefox
    @base_url = "http://dev.craft.dev/"
    @accept_next_alert = true
    @driver.manage.timeouts.implicit_wait = 30
    @verification_errors = []
  end
  
  after(:each) do
    @driver.quit
    @verification_errors.should == []
  end
  
  it "test_create_charge" do
    @driver.get(@base_url + "/stripey/charge")
    @driver.find_element(:name, "street").clear
    @driver.find_element(:name, "street").send_keys "33a Myles Rd"
    @driver.find_element(:name, "city").clear
    @driver.find_element(:name, "city").send_keys "Swan View"
    @driver.find_element(:name, "state").clear
    @driver.find_element(:name, "state").send_keys "WA"
    @driver.find_element(:name, "zip").clear
    @driver.find_element(:name, "zip").send_keys "6056"
    @driver.find_element(:css, "a.bfh-selectbox-toggle.form-control").click
    @driver.find_element(:css, "input.bfh-selectbox-filter.form-control").clear
    @driver.find_element(:css, "input.bfh-selectbox-filter.form-control").send_keys "australi"
    @driver.find_element(:link, "Australia").click
    @driver.find_element(:name, "email").clear
    @driver.find_element(:name, "email").send_keys "lukemh@gmail.com"
    @driver.find_element(:name, "cardholdername").clear
    @driver.find_element(:name, "cardholdername").send_keys "Luke Holder"
    Selenium::WebDriver::Support::Select.new(@driver.find_element(:xpath, "//form[@id='payment-form']/div/div/fieldset/fieldset/div[3]/div/div/select[2]")).select_by(:text, "2018")
    @driver.find_element(:id, "cvv").clear
    @driver.find_element(:id, "cvv").send_keys "123"
    @driver.find_element(:id, "amount").clear
    @driver.find_element(:id, "amount").send_keys "55"
    @driver.find_element(:css, "button.btn.btn-success").click
    (@driver.find_element(:css, "h2").text).should == "Success"
  end
  
  # def element_present?(how, what)
  #   ${receiver}.find_element(how, what)
  #   true
  # rescue Selenium::WebDriver::Error::NoSuchElementError
  #   false
  # end
  
  # def alert_present?()
  #   ${receiver}.switch_to.alert
  #   true
  # rescue Selenium::WebDriver::Error::NoAlertPresentError
  #   false
  # end
  
  def verify(&blk)
    yield
  rescue ExpectationNotMetError => ex
    @verification_errors << ex
  end
  
  # def close_alert_and_get_its_text(how, what)
  #   alert = ${receiver}.switch_to().alert()
  #   alert_text = alert.text
  #   if (@accept_next_alert) then
  #     alert.accept()
  #   else
  #     alert.dismiss()
  #   end
  #   alert_text
  # ensure
  #   @accept_next_alert = true
  # end
end
